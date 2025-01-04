<?php

class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Upsert a single trade into the database.
     * 
     * @param array $trade The trade data to be upserted.
     * @return bool True on success, False on failure.
     */
    public function upsertTrade($trade) {
        try {
            // Prepare the SQL statement for upsert operation
            $stmt = $this->db->prepare("
                INSERT INTO trades 
                (account_id, pair, order_type, volume, profit, open_price, stop_loss, take_profit, open_time, status, magic_number, remarks) 
                VALUES 
                (
                    (SELECT id FROM accounts WHERE login = :account_number), 
                    :pair, :order_type, :volume, :profit, :open_price, :stop_loss, :take_profit, :open_time, 'open', :magic_number, :remarks
                )
                ON DUPLICATE KEY UPDATE
                    profit = VALUES(profit),
                    stop_loss = VALUES(stop_loss),
                    take_profit = VALUES(take_profit),
                    volume = VALUES(volume),
                    status = 'open',
                    remarks = VALUES(remarks)
            ");

            // Bind parameters and execute the statement
            $stmt->execute([
                ':account_number' => $trade['account_number'],
                ':pair' => $trade['symbol'], // Updated to use 'symbol' instead of 'pair' for consistency
                ':order_type' => $trade['type'], // 'type' corresponds to the order type
                ':volume' => $trade['volume'],
                ':profit' => $trade['profit'],
                ':open_price' => $trade['open_price'],
                ':stop_loss' => $trade['stop_loss'],
                ':take_profit' => $trade['take_profit'],
                ':open_time' => $trade['open_time'],
                ':magic_number' => $trade['magic_number'],
                ':remarks' => $trade['remarks'] ?? null,
            ]);

            // Return success without logging
            return true;
        } catch (PDOException $e) {
            // Suppress logging and return failure
            return false;
        }
    }

    /**
     * Fetch trades for a specific account by login.
     * 
     * @param string $accountLogin The login of the account.
     * @return array The list of trades or an empty array if none found.
     */
    public function getTradesByAccount($accountLogin) {
        $stmt = $this->db->prepare("
            SELECT 
                t.id, t.pair, t.order_type, t.volume, t.profit, t.open_price, 
                t.stop_loss, t.take_profit, t.open_time, t.status, t.magic_number
            FROM trades t
            INNER JOIN accounts a ON t.account_id = a.id
            WHERE a.login = :login
        ");
        $stmt->execute([':login' => $accountLogin]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertGroupedTrade($accountNumber, $groupedTrade) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO aggregated_trades 
                (account_id, pair, order_type, volume, profit, prorated_open_price, magic_number, trade_count, currency_price) 
                VALUES 
                (
                    (SELECT id FROM accounts WHERE login = :account_number), 
                    :pair, :order_type, :volume, :profit, :prorated_open_price, :magic_number, :trade_count, :currency_price
                )
                ON DUPLICATE KEY UPDATE
                    volume = VALUES(volume),
                    profit = VALUES(profit),
                    prorated_open_price = VALUES(prorated_open_price),
                    trade_count = VALUES(trade_count),
                    currency_price = VALUES(currency_price)
            ");
    
            $stmt->execute([
                ':account_number' => $accountNumber,
                ':pair' => $groupedTrade['symbol'],
                ':order_type' => $groupedTrade['type'],
                ':volume' => $groupedTrade['total_volume'],
                ':profit' => $groupedTrade['total_profit'],
                ':prorated_open_price' => $groupedTrade['prorated_open_price'],
                ':magic_number' => $groupedTrade['magic_number'],
                ':trade_count' => $groupedTrade['trade_count'],
                ':currency_price' => $groupedTrade['currency_price'],
            ]);
    
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
}
