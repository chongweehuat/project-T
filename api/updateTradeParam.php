<?php
require_once '/var/www/db/database.php'; // Adjust the path as necessary
require_once '/var/www/models/TradesConfigModel.php';
require_once '/var/www/utils/Logger.php';

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

    $configId = $input['config_id'] ?? null;
    $groupId = $input['group_id'] ?? null;
    $param = $input['param'] ?? null;
    $value = $input['value'] ?? null;

    // Validate input
    $allowedParams = ['remark', 'auth_FT', 'auth_AT', 'auth_CP', 'auth_SL', 'auth_CL', 'stop_loss', 'take_profit'];
    if ((!$configId && !$groupId) || !$param || !in_array($param, $allowedParams) || ($param !== 'remark' && !is_numeric($value))) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid input."]);
        exit;
    }

    // Ensure numeric values are formatted correctly
    if (in_array($param, ['stop_loss', 'take_profit'])) {
        $value = round((float)$value, 5);
    } elseif (!in_array($param, ['remark'])) {
        $value = (int)$value;
    }

    $db = Database::connect("trade"); // Database connection
    $tradeConfigModel = new TradesConfigModel($db);
logMessage("updateTradeParam: $configId, $groupId, $param, $value\n");
    // Use upsertTradeParam for both cases
    $updated = $tradeConfigModel->upsertTradeParam($configId, $groupId, $param, $value);

    if ($updated) {
        echo json_encode([
            "status" => "success",
            "message" => ucfirst(str_replace('_', ' ', $param)) . " updated successfully."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update " . ucfirst(str_replace('_', ' ', $param)) . "."
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}
