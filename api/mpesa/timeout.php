<?php

header('Content-Type: application/json');

$timeoutData = file_get_contents('php://input');

file_put_contents(
    __DIR__ . '/logs/timeout_log.txt',
    date('Y-m-d H:i:s') . PHP_EOL .
    $timeoutData . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Timeout Received"
]);;