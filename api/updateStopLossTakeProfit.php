<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Invalid request method."]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $id = $input['id'] ?? null;
    $param = $input['param'] ?? null;
    $value = $input['value'] ?? null;

    // Validate input
    if (!$id || !$param || !in_array($param, ['stop_loss', 'take_profit']) || !is_numeric($value)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid input."]);
        exit;
    }

    // Ensure the value is formatted to 5 decimal places
    $value = round((float)$value, 5);

    $db = Database::connect("trade");
    $tradeConfigModel = new TradesConfigModel($db);

    // Update Stop Loss or Take Profit
    $updated = $tradeConfigModel->updateStopLossTakeProfit($id, $param, $value);

    if ($updated) {
        echo json_encode(["status" => "success", "message" => ucfirst(str_replace('_', ' ', $param)) . " updated successfully."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update " . ucfirst(str_replace('_', ' ', $param)) . "."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
