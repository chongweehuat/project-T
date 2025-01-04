<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';

// Set headers for JSON response
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if login parameter is provided
$login = $_GET['login'] ?? null;

if (!$login) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Login parameter is required."]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect('trade');
    $tradeModel = new TradeModel($db);

    // Fetch trades for the given account login
    $trades = $tradeModel->getTradesByAccount($login);

    if ($trades) {
        echo json_encode(["status" => "success", "data" => $trades]);
    } else {
        echo json_encode(["status" => "error", "message" => "No trades found for the account."]);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    exit;
}

?>
