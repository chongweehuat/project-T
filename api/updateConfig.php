<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    // 验证请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        exit;
    }

    // 解析输入数据
    $input = json_decode(file_get_contents("php://input"), true);

    // 验证必需字段
    $requiredFields = ['account_id', 'remark', 'auth_FT', 'auth_AT', 'auth_CP', 'auth_SL', 'auth_CL'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Missing required field: $field."]);
            exit;
        }
    }

    // 提取字段
    $accountId = intval($input['account_id']);
    $remark = htmlspecialchars($input['remark'], ENT_QUOTES, 'UTF-8');
    $auth_FT = intval($input['auth_FT']);
    $auth_AT = intval($input['auth_AT']);
    $auth_CP = intval($input['auth_CP']);
    $auth_SL = intval($input['auth_SL']);
    $auth_CL = intval($input['auth_CL']);

    // 连接数据库
    $db = Database::connect("trade");
    $tradesConfigModel = new TradesConfigModel($db);

    // 更新配置信息
    $updateResult = $tradesConfigModel->updateConfig($accountId, $remark, $auth_FT, $auth_AT, $auth_CP, $auth_SL, $auth_CL);

    if ($updateResult) {
        echo json_encode(["status" => "success", "message" => "Configuration updated successfully."]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(["status" => "error", "message" => "Failed to update configuration."]);
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
