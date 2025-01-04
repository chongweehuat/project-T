<?php
require_once '../config/database.php';

header('Content-Type: application/json');

// Validate input
$accountNumber = $_POST['account_number'] ?? null;

if (!$accountNumber) {
    echo json_encode(['status' => 'error', 'message' => 'Account number is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT risk_per_trade, max_loss, max_profit 
        FROM accounts 
        WHERE account_number = ?
    ");
    $stmt->execute([$accountNumber]);
    $riskConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($riskConfig) {
        echo json_encode(['status' => 'success'] + $riskConfig);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No risk configuration found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
