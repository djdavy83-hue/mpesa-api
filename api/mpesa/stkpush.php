<?php

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| M-PESA STK PUSH API
|--------------------------------------------------------------------------
| Independent Daraja STK Push
| Supports:
| - Paybill
| - Till Number
| - STK Push
| - Callback Tracking
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$consumerKey = "YOUR_CONSUMER_KEY";
$consumerSecret = "YOUR_CONSUMER_SECRET";

$shortcode = "174379"; // Paybill or Till
$passkey = "YOUR_PASSKEY";

$environment = "sandbox"; // sandbox OR production

/*
|--------------------------------------------------------------------------
| API URLS
|--------------------------------------------------------------------------
*/

if ($environment == "production") {

    $oauthURL = "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

    $stkURL = "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

} else {

    $oauthURL = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

    $stkURL = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
}

/*
|--------------------------------------------------------------------------
| RECEIVE JSON INPUT
|--------------------------------------------------------------------------
*/

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON input"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| VALIDATE INPUT
|--------------------------------------------------------------------------
*/

$phone = $input['phone'] ?? '';
$amount = $input['amount'] ?? 0;
$accountReference = $input['account_reference'] ?? 'NOBELLANET';
$transactionDesc = $input['description'] ?? 'Internet Payment';

if (empty($phone) || empty($amount)) {

    echo json_encode([
        "success" => false,
        "message" => "Phone and amount required"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| FORMAT PHONE
|--------------------------------------------------------------------------
*/

$phone = preg_replace('/[^0-9]/', '', $phone);

if (substr($phone, 0, 1) == '0') {

    $phone = '254' . substr($phone, 1);
}

if (substr($phone, 0, 3) != '254') {

    echo json_encode([
        "success" => false,
        "message" => "Invalid phone format"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| GENERATE ACCESS TOKEN
|--------------------------------------------------------------------------
*/

$credentials = base64_encode($consumerKey . ':' . $consumerSecret);

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $oauthURL);

curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials
]);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$tokenResponse = curl_exec($curl);

if (curl_errno($curl)) {

    echo json_encode([
        "success" => false,
        "message" => curl_error($curl)
    ]);

    exit;
}

curl_close($curl);

$tokenData = json_decode($tokenResponse);

if (!isset($tokenData->access_token)) {

    echo json_encode([
        "success" => false,
        "message" => "Failed to generate token",
        "response" => $tokenResponse
    ]);

    exit;
}

$accessToken = $tokenData->access_token;

/*
|--------------------------------------------------------------------------
| GENERATE PASSWORD
|--------------------------------------------------------------------------
*/

$timestamp = date('YmdHis');

$password = base64_encode(
    $shortcode .
    $passkey .
    $timestamp
);

/*
|--------------------------------------------------------------------------
| CALLBACK URL
|--------------------------------------------------------------------------
*/

$callbackURL = "https://yourdomain.com/api/mpesa/callback.php";

/*
|--------------------------------------------------------------------------
| STK PUSH DATA
|--------------------------------------------------------------------------
*/

$stkData = [

    "BusinessShortCode" => $shortcode,

    "Password" => $password,

    "Timestamp" => $timestamp,

    "TransactionType" => "CustomerPayBillOnline",

    "Amount" => intval($amount),

    "PartyA" => $phone,

    "PartyB" => $shortcode,

    "PhoneNumber" => $phone,

    "CallBackURL" => $callbackURL,

    "AccountReference" => $accountReference,

    "TransactionDesc" => $transactionDesc
];

/*
|--------------------------------------------------------------------------
| SEND STK PUSH
|--------------------------------------------------------------------------
*/

$curl = curl_init();

curl_setopt($curl, CURLOPT_URL, $stkURL);

curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken
]);

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

curl_setopt($curl, CURLOPT_POST, true);

curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stkData));

$response = curl_exec($curl);

if (curl_errno($curl)) {

    echo json_encode([
        "success" => false,
        "message" => curl_error($curl)
    ]);

    exit;
}

curl_close($curl);

/*
|--------------------------------------------------------------------------
| LOG RESPONSE
|--------------------------------------------------------------------------
*/

file_put_contents(
    __DIR__ . '/logs/stkpush_log.txt',
    date('Y-m-d H:i:s') . PHP_EOL .
    json_encode($stkData) . PHP_EOL .
    $response . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

/*
|--------------------------------------------------------------------------
| PROCESS RESPONSE
|--------------------------------------------------------------------------
*/

$responseData = json_decode($response, true);

if (isset($responseData['ResponseCode'])
    && $responseData['ResponseCode'] == "0") {

    $merchantRequestID = $responseData['MerchantRequestID'];

    $checkoutRequestID = $responseData['CheckoutRequestID'];

    /*
    |--------------------------------------------------------------------------
    | SAVE TO DATABASE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO tbl_mpesa_transactions (

            phone,
            amount,
            checkout_request_id,
            merchant_request_id,
            account_reference,
            transaction_desc,
            status

        )

        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->bind_param(
        "sdssss",
        $phone,
        $amount,
        $checkoutRequestID,
        $merchantRequestID,
        $accountReference,
        $transactionDesc
    );

    $stmt->execute();

    echo json_encode([

        "success" => true,

        "message" => "STK Push Sent Successfully",

        "data" => $responseData

    ]);

} else {

    echo json_encode([

        "success" => false,

        "message" => "STK Push Failed",

        "response" => $responseData

    ]);
}