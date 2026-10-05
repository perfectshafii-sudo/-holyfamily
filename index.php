<?php
// Set JSON response header for Beem Bpay
header('Content-Type: application/json');

// Include the standard RouterOS API PHP class
require_once('routeros_api.class.php');

// 1. Capture the webhook raw POST payload from Beem Bpay
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// Log incoming payments to a text file for debugging
file_put_contents('beem_log.txt', date('[Y-m-d H:i:s] ') . $raw_input . PHP_EOL, FILE_APPEND);

// Validate payload
if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "No data or invalid JSON payload received"]);
    exit;
}

// 2. Extract transaction variables from Beem payload
$status         = isset($data['status']) ? strtolower($data['status']) : '';
$transaction_id = $data['transactionID'] ?? $data['transaction_id'] ?? ('TXN' . time());
$phone          = $data['msisdn'] ?? $data['phone_number'] ?? '';
$amount         = $data['amount'] ?? 0;
$reference      = $data['referenceNumber'] ?? '';

// 3. Check if payment status is successful
if ($status === 'success' || $status === 'completed') {

    // =========================================================
    // MIKROTIK ROUTER CONNECTION SETTINGS
    // =========================================================
    $mikrotik_host = 'hja0aecqfv2.sn.mynetname.net'; // Your MikroTik Cloud DDNS
    $mikrotik_user = 'admin';                       // Your RouterOS API Username
    $mikrotik_pass = 'N8IMU9PRNK';                  // Your RouterOS API Password
    $mikrotik_port = 8728;                          // Standard API Port

    // User credentials created on MikroTik
    $username = $phone;               // Phone number acts as Hotspot Username
    $password = rand(1000, 9999);     // Generates a random 4-digit password
    $profile  = 'default';            // Hotspot profile configured in WinBox

    // Initialize RouterOS API
    $API = new RouterosAPI();
    $API->debug = false;

    // Connect to MikroTik Router
    if ($API->connect($mikrotik_host, $mikrotik_user, $mikrotik_pass, $mikrotik_port)) {

        // Command: /ip hotspot user add
        $API->comm("/ip/hotspot/user/add", array(
            "name"     => (string)$username,
            "password" => (string)$password,
            "profile"  => $profile,
            "comment"  => "Beem TXN: " . $transaction_id . " | Amt: " . $amount
        ));

        // Close connection
        $API->disconnect();

        // Respond to Beem Bpay with HTTP 200 OK
        http_response_code(200);
        echo json_encode([
            "status"          => true,
            "statusMessage"   => "User provisioned on MikroTik successfully",
            "transactionID"   => $transaction_id,
            "referenceNumber" => $reference
        ]);
        exit;

    } else {
        // Log connection failures
        file_put_contents('beem_error.txt', date('[Y-m-d H:i:s] ') . "Could not connect to MikroTik at " . $mikrotik_host . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(["status" => false, "message" => "Unable to connect to MikroTik router"]);
        exit;
    }

} else {
    // Payment was cancelled or failed on customer phone
    http_response_code(200);
    echo json_encode([
        "status"        => false,
        "statusMessage" => "Payment not completed. Status: " . $status
    ]);
    exit;
}
?>