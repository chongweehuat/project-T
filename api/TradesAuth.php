<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';
require_once '/var/www/utils/Logger.php';

header("Content-Type: application/json; charset=UTF-8");
$debug=true;
logMessage(var_export($_POST, true));
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

    logMessage("Authorization request received: account_id=$accountId, magic_number=$magicNumber, pair=$pair, order_type=$orderType");

    // Connect to the database
    $db = Database::connect("trade");
    $tradesConfigModel = new TradesConfigModel($db);

    // Check authorization using the model
    $isAuthorized = $tradesConfigModel->checkAuthorization($accountId, $magicNumber, $pair, $orderType);

    if ($isAuthorized) {
        logMessage("Authorization granted for account_id=$accountId, magic_number=$magicNumber.");
        http_response_code(200); // OK
        echo json_encode(["status" => "success", "message" => "ALLOW"]);
    } else {
        logMessage("Authorization denied for account_id=$accountId, magic_number=$magicNumber.");
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "DENY"]);
    }
} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
    exit;
}
?>
