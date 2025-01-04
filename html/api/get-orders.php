<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Validate input
$accountNumber = $_POST['account_number'] ?? null;

if (!$accountNumber) {
    echo json_encode(['status' => 'error', 'message' => 'Account number is required']);
    exit;
}

try {
    // Fetch orders for the account number
    $stmt = $pdo->prepare("
        SELECT 
            t.pair, 
            t.order_type, 
            t.lots, 
            t.stop_loss, 
            t.take_profit, 
            t.pending_order_price, 
            t.transaction_id 
        FROM transactions t
        INNER JOIN accounts a ON t.account_id = a.account_id
        WHERE a.account_number = ? 
          AND t.status = 'PENDING'
    ");
    $stmt->execute([$accountNumber]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($orders) {
        echo json_encode([
            'status' => 'success',
            'orders' => $orders
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'orders' => []
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
