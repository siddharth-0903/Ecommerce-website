<?php
header('Content-Type: application/json');
require 'vendor/autoload.php';

use Razorpay\Api\Api;

$keyId = 'rzp_test_RATdjTid0lID7f';       // your test key
$keySecret = 'WhYHndO3nTzLEU6UdHJc7X9M';    // your test secret

try {
    $api = new Api($keyId, $keySecret);

    // Get the amount from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $amount = isset($input['amount']) ? (int)$input['amount'] : 0;

    if ($amount <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid amount'
        ]);
        exit();
    }

    $orderData = [
        'receipt'         => 'rcptid_' . rand(1000, 9999),
        'amount'          => $amount, // Use the amount from request
        'currency'        => 'INR',
        'payment_capture' => 1
    ];

    $razorpayOrder = $api->order->create($orderData);

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => $razorpayOrder->id,
            'amount' => $razorpayOrder->amount,
            'currency' => $razorpayOrder->currency,
            'receipt' => $razorpayOrder->receipt
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Razorpay Error',
        'details' => $e->getMessage()
    ]);
}
