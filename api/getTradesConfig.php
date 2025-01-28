<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeConfigModel.php';
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

// Validate `id`
$configId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$configId) {
    echo json_encode(["status" => "error", "message" => "Missing or invalid trades_config id"]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect("trade");
    $tradeConfigModel = new TradeConfigModel($db);

    // Fetch trades configuration by `id`
    $tradeConfig = $tradeConfigModel->getTradeConfigById($configId);

    if (!$tradeConfig) {
        echo json_encode(["status" => "error", "message" => "Trade configuration not found"]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Trade configuration retrieved successfully.",
        "data" => $tradeConfig,
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to fetch trade configuration: " . $e->getMessage(),
    ]);
}
