<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Validate input
$accountId = $_GET['account_id'] ?? null;

if (!$accountId) {
    echo json_encode(['status' => 'error', 'message' => 'Account ID is required']);
    exit;
}

try {
    // Fetch account details
    $stmt = $pdo->prepare("
        SELECT balance, risk_per_trade, max_loss, max_profit, daily_limit, total_limit 
        FROM accounts 
        WHERE account_id = ?
    ");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }

    // Calculate default lot size
    $defaultRisk = $account['balance'] * $account['risk_per_trade'];
    $defaultLotSize = $defaultRisk / 1000; // Assuming $1000 per lot risk (adjust as needed)

    echo json_encode([
        'status' => 'success',
        'account' => $account,
        'default_lot_size' => round($defaultLotSize, 2) // Round to two decimal places
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
