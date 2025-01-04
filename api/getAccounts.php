<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/AccountModel.php';

header('Access-Control-Allow-Origin: https://sa.my369.click');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect('trade');
    $accountModel = new AccountModel($db);

    // Fetch all accounts
    $accounts = $accountModel->getAllAccounts();

    // Return accounts as JSON
    echo json_encode(['status' => 'success', 'data' => $accounts]);
} catch (Exception $e) {
    // Log the error
    error_log($e->getMessage(), 3, '/var/www/api/logs/debug.log');

    // Return an error response
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve accounts.']);
}
?>
