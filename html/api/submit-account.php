<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Ensure the POST data is parsed correctly
$data = $_POST;

// Validate required fields
$requiredFields = ['ea_version', 'account_number', 'account_name', 'broker_name', 'leverage', 'balance'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "$field is required"]);
        exit;
    }
}

// Calculate limits based on balance
$balance = (float)$data['balance'];
$dailyLimit = $balance * 0.05;
$totalLimit = $balance * 0.1;
$maxTradeRisk = $balance * 0.01;

try {
    $pdo->beginTransaction();

    // Check if account exists
    $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE account_number = ?");
    $stmt->execute([$data['account_number']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        // Update existing account
        $stmt = $pdo->prepare(
            "UPDATE accounts 
             SET account_name = ?, balance = ?, prop_firm = ?, daily_limit = ?, total_limit = ?, max_trade_risk = ?, updated_at = NOW()
             WHERE account_id = ?"
        );
        $stmt->execute([
            $data['account_name'],
            $balance,
            $data['broker_name'],
            $dailyLimit,
            $totalLimit,
            $maxTradeRisk,
            $account['account_id']
        ]);
    } else {
        // Insert new account
        $stmt = $pdo->prepare(
            "INSERT INTO accounts 
             (account_number, account_name, balance, prop_firm, daily_limit, total_limit, max_trade_risk, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $data['account_number'],
            $data['account_name'],
            $balance,
            $data['broker_name'],
            $dailyLimit,
            $totalLimit,
            $maxTradeRisk
        ]);
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Account data processed successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database Error']);
}
