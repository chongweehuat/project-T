<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php'; // Use Logger utility

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Capture the account ID from the query string
$accountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;

// Validate the account ID
if (!$accountId) {
    logMessage("Invalid or missing account_id parameter.");
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or missing account_id parameter."
    ]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect("trade");
    $tradeModel = new TradeModel($db);

    // Fetch grouped trades for the specified account ID
    $trades = $tradeModel->getGroupedTrades($accountId);

    // Check if data exists
    if (!$trades) {
        logMessage("No grouped trades found for account_id={$accountId}");
        echo json_encode([
            "status" => "success",
            "data" => []
        ]);
        exit;
    }

    // Return the grouped trades data
    echo json_encode([
        "status" => "success",
        "data" => $trades
    ]);
} catch (Exception $e) {
    // Log and handle server errors
    logMessage("Error fetching grouped trades: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to fetch grouped trades."
    ]);
}
