<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Define a debug log function (currently disabled)
function debugLog($message) {
    //Debug logging is disabled. Uncomment the following lines to enable logging.
    $logFile = '/var/www/api/logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Function to clean the $_POST data
function cleanPostData($postData) {
    $cleanedData = [];
    foreach ($postData as $key => $value) {
        $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value; // Remove trailing null bytes
        $cleanedData[$key] = $cleanedValue;
    }
    return $cleanedData;
}

// Clean and log the incoming POST data
$_POST = cleanPostData($_POST);
//debugLog("Received POST data: " . var_export($_POST, true));

// Extract and sanitize POST parameters
$data = [
    'ea_version' => $_POST['ea_version'] ?? null,
    'account_number' => $_POST['account_number'] ?? null,
    'symbol' => $_POST['symbol'] ?? null,
    'type' => $_POST['type'] ?? null,
    'volume' => isset($_POST['volume']) ? floatval($_POST['volume']) : null,
    'profit' => isset($_POST['profit']) ? floatval($_POST['profit']) : null,
    'open_price' => isset($_POST['open_price']) ? floatval($_POST['open_price']) : null,
    'stop_loss' => isset($_POST['stop_loss']) ? floatval($_POST['stop_loss']) : null,
    'take_profit' => isset($_POST['take_profit']) ? floatval($_POST['take_profit']) : null,
    'open_time' => isset($_POST['open_time']) ? rtrim($_POST['open_time'], "\0") : null, // Clean open_time
    'magic_number' => isset($_POST['magic_number']) ? intval($_POST['magic_number']) : null,
    'remarks' => $_POST['remarks'] ?? null,
];

// Convert the open_time to MySQL datetime format
function convertToMySQLDateTime($timeString) {
    if (!$timeString) {
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y.m.d H:i', $timeString);
    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : null;
}

$data['open_time'] = convertToMySQLDateTime($data['open_time']);

// Validate required data
if (!$data['account_number'] || !$data['symbol'] || !$data['type'] || !isset($data['magic_number']) || !$data['open_time']) {
    http_response_code(400); // Bad Request
    debugLog("Invalid or missing trade data: " . var_export($data, true));
    echo json_encode(["status" => "error", "message" => "Invalid or missing trade data."]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect('trade');
    $tradeModel = new TradeModel($db);

    // Upsert the trade into the database
    if ($tradeModel->upsertTrade($data)) {
        http_response_code(200);
        // debugLog("Trade data processed successfully for symbol: " . $data['symbol']);
        echo json_encode(["status" => "success", "message" => "Trade data processed successfully."]);
    } else {
        http_response_code(400); // Bad Request
        debugLog("Failed to process trade data for symbol: " . $data['symbol']);
        echo json_encode(["status" => "error", "message" => "Failed to process trade data."]);
    }
} catch (PDOException $e) {
    debugLog("Database error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error."]);
    exit;
}
