<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    // 验证请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        exit;
    }

    // 验证 `account_id` 参数
    $accountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;
    if (!$accountId) {
        http_response_code(400); // Bad Request
        echo json_encode(["status" => "error", "message" => "Missing or invalid account_id parameter."]);
        exit;
    }

    // 连接数据库
    $db = Database::connect("trade");
    $tradesConfigModel = new TradesConfigModel($db);

    // 获取配置信息
    $config = $tradesConfigModel->getConfigByAccountId($accountId);

    if ($config) {
        echo json_encode(["status" => "success", "data" => $config]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(["status" => "error", "message" => "Configuration not found for account_id: $accountId."]);
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
