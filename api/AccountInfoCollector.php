<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/AccountModel.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Define a debug log function (disabled)
function debugLog($message) {
    // Debug log is currently disabled. Uncomment the following lines to enable it.
    // $logFile = '/var/www/api/logs/debug.log';
    // $timestamp = date('Y-m-d H:i:s');
    // file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Log incoming POST data (disabled)
// debugLog("Received POST data: " . var_export($_POST, 1));

// Extract POST parameters
$data = [
    'ea_version' => $_POST['ea_version'] ?? null,
    'account_number' => $_POST['account_number'] ?? null,
    'account_name' => $_POST['account_name'] ?? null,
    'broker_name' => $_POST['broker_name'] ?? null,
    'leverage' => isset($_POST['leverage']) ? intval($_POST['leverage']) : null,
    'balance' => isset($_POST['balance']) ? floatval($_POST['balance']) : null,
    'equity' => isset($_POST['equity']) ? floatval($_POST['equity']) : null,
    'free_margin' => isset($_POST['free_margin']) ? floatval($_POST['free_margin']) : null,
];

// Validate required data
if (
    !$data['account_number'] || 
    !$data['account_name'] || 
    !$data['broker_name'] || 
    $data['leverage'] === null || 
    $data['balance'] === null || 
    $data['equity'] === null || 
    $data['free_margin'] === null
) {
    http_response_code(400); // Bad Request
    $errorMessage = "Invalid or missing data.";
    // debugLog("Error: $errorMessage");
    echo json_encode(["status" => "error", "message" => $errorMessage]);
    exit;
}

try {
    // Connect to the database
    // debugLog("Connecting to the database...");
    $db = Database::connect('trade');

    // Call the AccountModel to handle the business logic
    $accountModel = new AccountModel($db);
    $accountModel->upsertAccount($data);

    // Send success response
    http_response_code(200);
    // debugLog("Account data processed successfully for login: " . $data['account_number']);
    echo json_encode(["status" => "success", "message" => "Account data processed successfully."]);
} catch (PDOException $e) {
    // Handle database errors
    $errorMessage = "Database error: " . $e->getMessage();
    // debugLog($errorMessage);
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error."]);
    exit;
}
