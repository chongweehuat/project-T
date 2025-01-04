<?php
// html/api/get-pair-defaults.php

require_once './config/database.php';

header('Content-Type: application/json');

$pair = $_GET['pair'] ?? '';

if (!$pair) {
    echo json_encode(['success' => false, 'message' => 'Pair not provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT bid_price FROM currency_pairs WHERE pair = ?");
    $stmt->execute([$pair]);
    $pairData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pairData) {
        $bidPrice = $pairData['bid_price'];
        echo json_encode([
            'success' => true,
            'defaults' => [
                'lots' => 0.01,
                'stop_loss' => round($bidPrice * 0.95, 5), // Example: SL at 5% below bid
                'take_profit' => round($bidPrice * 1.05, 5), // Example: TP at 5% above bid
            ],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pair not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching pair data: ' . $e->getMessage()]);
}
