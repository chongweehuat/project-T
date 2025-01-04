<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$pair = $_GET['pair'] ?? null;
$accountId = $_GET['account_id'] ?? null;

if (!$pair || !$accountId) {
    echo json_encode(['status' => 'error', 'message' => 'Pair and Account ID are required']);
    exit;
}

try {
    // Fetch account and pair details
    $accountStmt = $pdo->prepare("SELECT balance, risk_per_trade FROM accounts WHERE account_id = ?");
    $accountStmt->execute([$accountId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

    $priceStmt = $pdo->prepare("SELECT bid_price, ask_price FROM currency_pairs WHERE pair = ?");
    $priceStmt->execute([$pair]);
    $prices = $priceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || !$prices) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid account or pair']);
        exit;
    }

    // Calculate default values
    $pipValue = 0.01; // Adjust based on pair
    $stopLossPips = 50; // Example stop-loss pips
    $takeProfitPips = 250; // Example take-profit pips

    $entryPrice = $prices['bid_price'];
    $defaultRisk = $account['balance'] * $account['risk_per_trade'];
    $defaultLotSize = $defaultRisk / ($pipValue * $stopLossPips);
    $defaultSL = $entryPrice - ($stopLossPips * $pipValue);
    $defaultTP = $entryPrice + ($takeProfitPips * $pipValue);

    echo json_encode([
        'status' => 'success',
        'default_lot_size' => round($defaultLotSize, 2),
        'default_sl' => round($defaultSL, 5),
        'default_tp' => round($defaultTP, 5),
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
