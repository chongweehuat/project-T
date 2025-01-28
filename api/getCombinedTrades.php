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
    $db = Database::connect("trade");
    $configModel = new TradesConfigModel($db);

    $accountId = intval($_GET['account_id'] ?? 0);

    // Fetch trades_config with group_id
    $configsWithGroup = $configModel->getConfigsWithGroup($accountId);

    // Fetch trades_config without group_id
    $configsWithoutGroup = $configModel->getConfigsWithoutGroup($accountId);

    // Combine and sort data
    $combinedData = array_merge($configsWithGroup, $configsWithoutGroup);
    usort($combinedData, function ($a, $b) {
        return $a['magic_number'] <=> $b['magic_number']
            ?: $a['pair'] <=> $b['pair']
            ?: strcmp($a['order_type'], $b['order_type']);
    });

    echo json_encode(["status" => "success", "data" => $combinedData]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
