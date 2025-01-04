<?php
class OrderModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Fetch all accounts
    public function getAccounts() {
        $stmt = $this->pdo->query("SELECT account_id AS id, account_name AS name FROM accounts");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch risk status for a specific account
    public function getRiskStatus($accountId) {
        $stmt = $this->pdo->prepare("SELECT * FROM risk_status WHERE account_id = :account_id");
        $stmt->execute([':account_id' => $accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch open orders for a specific account
    public function getOpenOrders($accountId) {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE account_id = :account_id");
        $stmt->execute([':account_id' => $accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Insert a new order
    public function placeOrder($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (account_id, pair, type, lots, stop_loss, take_profit, pending_order_price, remark)
            VALUES (:account_id, :pair, :type, :lots, :stop_loss, :take_profit, :pending_order_price, :remark)
        ");
        return $stmt->execute($data);
    }
}
?>
