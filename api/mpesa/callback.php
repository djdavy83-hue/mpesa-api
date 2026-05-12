<?php

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

$callbackJSON = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/logs/callback_log.txt',
    date('Y-m-d H:i:s') . PHP_EOL .
    $callbackJSON . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$data = json_decode($callbackJSON, true);

if (!$data) {

    echo json_encode([
        "ResultCode" => 1,
        "ResultDesc" => "Invalid JSON"
    ]);

    exit;
}

$stkCallback = $data['Body']['stkCallback'] ?? null;

if (!$stkCallback) {

    echo json_encode([
        "ResultCode" => 1,
        "ResultDesc" => "Missing Callback"
    ]);

    exit;
}

$resultCode = $stkCallback['ResultCode'];
$resultDesc = $stkCallback['ResultDesc'];

$merchantRequestID = $stkCallback['MerchantRequestID'] ?? '';
$checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? '';

$status = "failed";

$receipt = null;
$phone = null;
$amount = 0;
$transactionDate = null;

if ($resultCode == 0) {

    $status = "paid";

    $metadata = $stkCallback['CallbackMetadata']['Item'];

    foreach ($metadata as $item) {

        if ($item['Name'] == 'MpesaReceiptNumber') {

            $receipt = $item['Value'];

        }

        if ($item['Name'] == 'PhoneNumber') {

            $phone = $item['Value'];

        }

        if ($item['Name'] == 'Amount') {

            $amount = $item['Value'];

        }

        if ($item['Name'] == 'TransactionDate') {

            $transactionDate = $item['Value'];

        }

    }

}

$stmt = $conn->prepare("
    UPDATE tbl_mpesa_transactions
    SET
        mpesa_receipt_number=?,
        transaction_date=?,
        result_code=?,
        result_desc=?,
        callback_data=?,
        status=?,
        paid_at=NOW()
    WHERE checkout_request_id=?
");

$stmt->bind_param(
    "sssssss",
    $receipt,
    $transactionDate,
    $resultCode,
    $resultDesc,
    $callbackJSON,
    $status,
    $checkoutRequestID
);

$stmt->execute();

echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Accepted"
]);