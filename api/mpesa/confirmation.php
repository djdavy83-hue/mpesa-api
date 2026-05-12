<?php

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

$confirmationData = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/logs/confirmation_log.txt',
    date('Y-m-d H:i:s') . PHP_EOL .
    $confirmationData . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$data = json_decode($confirmationData, true);

$transID = $data['TransID'] ?? '';
$amount = $data['TransAmount'] ?? 0;
$phone = $data['MSISDN'] ?? '';
$billRef = $data['BillRefNumber'] ?? '';

$stmt = $conn->prepare("
    INSERT INTO tbl_mpesa_transactions (
        phone,
        amount,
        mpesa_receipt_number,
        account_reference,
        status,
        callback_data,
        paid_at
    )
    VALUES (?, ?, ?, ?, 'paid', ?, NOW())
");

$stmt->bind_param(
    "sdsss",
    $phone,
    $amount,
    $transID,
    $billRef,
    $confirmationData
);

$stmt->execute();

echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Success"
]);