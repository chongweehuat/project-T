<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php';

// Validate the required structure of the incoming request
if (!isset($_POST['ea_version'], $_POST['account_id'], $_POST['trades']) || !is_array($_POST['trades'])) {
    logMessage("Invalid input structure: Missing required fields.");
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Invalid input structure."]);
    exit;
}

// Extract essential data
$accountId = intval($_POST['account_id']);
$trades = $_POST['trades'];

try {
    // Connect to the database
    $db = Database::connect("trade");
    $tradeModel = new TradeModel($db);

    // Synchronize trades using the model
    $result = $tradeModel->syncTrades($accountId, $trades);

    if ($result) {
        logMessage("Trades synced successfully for account_id: $accountId");
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Trades synced successfully."]);
    } else {
        logMessage("Failed to sync trades for account_id: $accountId");
        http_response_code(500); // Internal Server Error
        echo json_encode(["status" => "error", "message" => "Failed to sync trades."]);
    }
} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
    exit;
}

