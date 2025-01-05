<?php
require_once '../models/TradeModel.php';
require_once '../config/Database.php';

// Set response headers
header('Content-Type: application/json');

// Function to clean incoming POST data
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

// Extract account_id and trades array
$accountId = isset($_POST['account_id']) ? intval($_POST['account_id']) : null;
$trades = $_POST['trades'] ?? null;

// Validate input
if (!$accountId || !is_array($trades)) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Invalid or missing data."]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect();
    $tradeModel = new TradeModel($db);

    // Delete all open trades for the given account_id
    $tradeModel->deleteTradesByAccountId($accountId);

    // Insert the new set of trades
    foreach ($trades as $trade) {
        $trade['account_id'] = $accountId; // Ensure account_id is included
        $tradeModel->insertTrade($trade);
    }

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Trades synced successfully."]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
    exit;
}
?>
