<?php
// html/controller/OrderController.php

class OrderController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function fetchAvailablePairs() {
        $stmt = $this->pdo->query("SELECT pair FROM currency_pairs ORDER BY pair ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function index() {
        // Fetch accounts
        $accounts = $this->fetchAccounts();

        // Fetch available pairs
        $currencyPairs = $this->pdo->query("SELECT pair, bid_price, ask_price FROM currency_pairs")->fetchAll(PDO::FETCH_ASSOC);

        // Build pairPrices array
        $pairPrices = [];
        foreach ($currencyPairs as $pair) {
            $pairPrices[$pair['pair']] = [
                'bid_price' => $pair['bid_price'],
                'ask_price' => $pair['ask_price'],
            ];
        }

        // Fetch risk status and open orders for the selected account
        $accountId = $_GET['account_id'] ?? null;
        $riskStatus = null;
        $openOrders = [];
        if ($accountId) {
            $riskStatus = $this->fetchRiskStatus($accountId);
            $openOrders = $this->fetchOpenOrders($accountId);
        }

        // Calculate price offsets
        $riskMultiplier = 2;
        $priceOffsets = $this->calculatePriceOffsets($accounts, $currencyPairs, $riskMultiplier);

        // Pass data to the view
        require_once 'views/OrderView.php';
    }

    public function placeOrder() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accountId = $_POST['account_id'];
            $pair = $_POST['pair'];
            $lots = (float)$_POST['lots'];
            $stopLoss = $_POST['stop_loss'] !== '' ? (float)$_POST['stop_loss'] : null;
            $takeProfit = $_POST['take_profit'] !== '' ? (float)$_POST['take_profit'] : null;
            $pendingOrderPrice = $_POST['pending_order_price'] !== '' ? (float)$_POST['pending_order_price'] : null; // Handle empty value
            $remark = $_POST['remark'] ?? '';

            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO transactions (account_id, pair, lots, stop_loss, take_profit, pending_order_price, remark, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())
                ");
                $stmt->execute([$accountId, $pair, $lots, $stopLoss, $takeProfit, $pendingOrderPrice, $remark]);
                header('Location: /?account_id=' . $accountId . '&message=success');
                exit;
            } catch (PDOException $e) {
                echo "Error: " . $e->getMessage();
            }
        }
    }


    private function fetchAccounts() {
        $stmt = $this->pdo->query("
            SELECT 
                account_id AS id, 
                CONCAT(account_number, ' - ', prop_firm) AS name,
                balance, 
                risk_per_trade 
            FROM accounts
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchRiskStatus($accountId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                daily_limit AS max_daily_drawdown, 
                total_limit AS max_total_drawdown, 
                0 AS daily_drawdown, 
                0 AS total_drawdown, 
                0 AS warnings, 
                0 AS stopped
            FROM accounts
            WHERE account_id = ?
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchOpenOrders($accountId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pair, 
                lots, 
                stop_loss, 
                take_profit, 
                status 
            FROM transactions
            WHERE account_id = ? AND status = 'PENDING'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculatePriceOffsets($accounts, $currencyPairs, $riskMultiplier) {
        $priceOffsets = [];

        foreach ($accounts as $account) {
            $balance = (float)$account['balance'];
            $riskPerTrade = (float)$account['risk_per_trade'];

            foreach ($currencyPairs as $pair) {
                $pipValue = $this->getPipValue($pair['pair']);
                $bidPrice = (float)$pair['bid_price'];

                // Assume a standard SL in pips (adjust as needed for different markets)
                $standardSLPips = 50; // Example: 50 pips

                // Lot size calculation
                $lotSize = ($balance * $riskPerTrade) / ($standardSLPips * $pipValue);

                // Calculate SL and TP
                $stopLoss = $standardSLPips * $pipValue;
                $takeProfit = $stopLoss * $riskMultiplier;

                // Store offsets
                $priceOffsets[$pair['pair']] = [
                    'sl' => $stopLoss,
                    'tp' => $takeProfit,
                ];
            }
        }

        return $priceOffsets;
    }

    private function getPipValue($pair) {
        if (strpos($pair, 'JPY') !== false) {
            return 0.01; // JPY pairs have pip value of 0.01
        }
        if ($pair === 'BTCUSD') {
            return 1.0; // Crypto pairs have pip value of 1.0
        }
        return 0.0001; // Standard pip value for most forex pairs
    }
}
