<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Validate input
$accountId = $_POST['account_id'] ?? null;
$pair = $_POST['pair'] ?? null;
$lots = $_POST['lots'] ?? null;
$orderType = $_POST['order_type'] ?? null;
$pendingPrice = $_POST['pending_order_price'] ?? null;
$stopLoss = $_POST['stop_loss'] ?? null;
$takeProfit = $_POST['take_profit'] ?? null;

if (!$accountId || !$pair || !$lots || !$orderType) {
    echo json_encode(['status' => 'error', 'message' => 'Required fields are missing']);
    exit;
}

try {
    // Fetch risk configuration for the account
    $stmt = $pdo->prepare("
        SELECT max_loss, max_profit 
        FROM accounts 
        WHERE account_id = ?
    ");
    $stmt->execute([$accountId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['status' => 'error', 'message' => 'Account not found']);
        exit;
    }

    $maxLoss = $account['max_loss'];
    $maxProfit = $account['max_profit'];

    // Validate Stop Loss
    if ($stopLoss) {
        $currentPrice = getCurrentPrice($pair, $orderType);
        $riskInPips = abs($currentPrice - $stopLoss);

        if ($riskInPips > $maxLoss) {
            echo json_encode(['status' => 'error', 'message' => 'SL exceeds max risk']);
            exit;
        }
    }

    // Validate Take Profit
    if ($takeProfit) {
        $currentPrice = getCurrentPrice($pair, $orderType);
        $rewardInPips = abs($currentPrice - $takeProfit);

        if ($rewardInPips > $maxProfit) {
            echo json_encode(['status' => 'error', 'message' => 'TP exceeds max profit']);
            exit;
        }
    }

    // Insert order into the database
    $stmt = $pdo->prepare("
        INSERT INTO transactions (account_id, pair, order_type, lots, stop_loss, take_profit, pending_order_price, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
    ");
    $stmt->execute([$accountId, $pair, $orderType, $lots, $stopLoss, $takeProfit, $pendingPrice]);

    echo json_encode(['status' => 'success', 'message' => 'Order placed successfully']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function getCurrentPrice($pair, $orderType) {
    // Simulate fetching the current price based on order type
    // Replace this logic with actual price fetching if needed
    return $orderType === 'BUY' || $orderType === 'BUY_STOP' 
        ? 1.2000  // Example ask price
        : 1.1995; // Example bid price
}
?>
