<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Validate input
$orderId = $_POST['order_id'] ?? null;
$stopLoss = $_POST['stop_loss'] ?? null;
$takeProfit = $_POST['take_profit'] ?? null;

if (!$orderId || $stopLoss === null || $takeProfit === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Order ID, Stop Loss, and Take Profit are required'
    ]);
    exit;
}

try {
    // Ensure the order exists before updating
    $checkStmt = $pdo->prepare("SELECT transaction_id FROM transactions WHERE transaction_id = ?");
    $checkStmt->execute([$orderId]);
    $orderExists = $checkStmt->fetchColumn();

    if (!$orderExists) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Order not found'
        ]);
        exit;
    }

    // Update the transaction
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET stop_loss = ?, take_profit = ?, status = 'EXECUTED', updated_at = NOW() 
        WHERE transaction_id = ?
    ");
    $stmt->execute([$stopLoss, $takeProfit, $orderId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Order updated successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No changes were made to the order'
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
