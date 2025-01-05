<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php';

$debug = true; // Enable or disable debug logging

/**
 * Clean incoming POST data to remove unwanted characters.
 */
function cleanPostData($data) {
    $cleanedData = [];
    foreach ($data as $key => $value) {
        $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value; // Remove trailing null bytes
        $cleanedData[$key] = $cleanedValue;
    }
    return $cleanedData;
}

// Clean the incoming POST data
$_POST = cleanPostData($_POST);

// Validate the required fields in the incoming request
$requiredFields = [
    'account_id', 'ticket', 'pair', 'order_type', 
    'volume', 'open_price', 'profit', 'open_time', 
    'bid_price', 'ask_price', 'magic_number'
];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field])) {
        logMessage("Missing required field: $field");
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
        exit;
    }
}

// Extract account_id from the incoming data
$accountId = intval($_POST['account_id']);

try {
    // Connect to the database
    $db = Database::connect("trade");
    $tradeModel = new TradeModel($db);

    // Synchronize the single trade
    $result = $tradeModel->syncSingleTrade($accountId, $_POST);

    if ($result) {
        logMessage("Trade synced successfully: " . json_encode($_POST));
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Trade synced successfully."]);
    } else {
        logMessage("Failed to sync trade: " . json_encode($_POST));
        http_response_code(500); // Internal Server Error
        echo json_encode(["status" => "error", "message" => "Failed to sync trade."]);
    }

} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
    exit;
}

