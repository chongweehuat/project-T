<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';
require_once '/var/www/utils/Logger.php';

header("Content-Type: application/json; charset=UTF-8");

//$debug = true; // Enable debugging for logging

try {
    // Ensure the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logMessage("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405); // Method Not Allowed
        echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        exit;
    }

    // Validate required fields
    $requiredFields = ['account_id', 'magic_number', 'pair', 'order_type'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            logMessage("Missing required field: $field");
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
            exit;
        }
    }

    // Extract and sanitize input data
    $accountId = intval($_POST['account_id']);
    $magicNumber = intval($_POST['magic_number']);
    $pair = htmlspecialchars($_POST['pair'], ENT_QUOTES, 'UTF-8');
    $orderType = htmlspecialchars($_POST['order_type'], ENT_QUOTES, 'UTF-8');
    $remark = isset($_POST['remark']) ? htmlspecialchars($_POST['remark'], ENT_QUOTES, 'UTF-8') : null;

    logMessage("Received data: account_id=$accountId, magic_number=$magicNumber, pair=$pair, order_type=$orderType, remark=$remark");

    // Connect to the database
    $db = Database::connect("trade");
    $tradesConfigModel = new TradesConfigModel($db);

    // Store configuration in the database
    $result = $tradesConfigModel->storeConfig($accountId, $magicNumber, $pair, $orderType, $remark);

    if ($result) {
        logMessage("Configuration stored successfully for account_id: $accountId, magic_number: $magicNumber.");
        http_response_code(200); // OK
        echo json_encode(["status" => "success", "message" => "Configuration stored successfully."]);
    } else {
        logMessage("Failed to store configuration for account_id: $accountId, magic_number: $magicNumber.");
        http_response_code(500); // Internal Server Error
        echo json_encode(["status" => "error", "message" => "Failed to store configuration."]);
    }
} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
    exit;
}
?>
