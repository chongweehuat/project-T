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

    if (!$id || !$param || !in_array($param, ['auth_FT', 'auth_AT', 'auth_CP', 'auth_SL', 'auth_CL']) || !is_numeric($value)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid input."]);
        exit;
    }

    $db = Database::connect("trade");
    $tradeConfigModel = new TradesConfigModel($db);
    $updated = $tradeConfigModel->updateAuthParam($id, $param, (int)$value);

    if ($updated) {
        echo json_encode(["status" => "success", "message" => "Authorization parameter updated."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update authorization parameter."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
