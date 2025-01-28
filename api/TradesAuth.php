<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradesConfigModel.php';

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed."]);
        exit;
    }

    // 提取字段
    $accountId = intval($_POST['account_id']);
    $magicNumber = intval($_POST['magic_number']);
    $pair = htmlspecialchars($_POST['pair'], ENT_QUOTES, 'UTF-8');
    $orderType = htmlspecialchars($_POST['order_type'], ENT_QUOTES, 'UTF-8');
    $remark = htmlspecialchars($_POST['remark'], ENT_QUOTES, 'UTF-8');

    $db = Database::connect("trade");
    $tradesConfigModel = new TradesConfigModel($db);

    // 更新 remark
    $tradesConfigModel->updateRemark($accountId, $magicNumber, $remark);

    // 获取授权配置
    $authConfig = $tradesConfigModel->getAuthorizationConfig($accountId, $magicNumber, $pair, $orderType);

    if ($authConfig) {
        http_response_code(200);
        echo json_encode($authConfig);
    } else {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Authorization configuration not found."]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error occurred."]);
}
?>
