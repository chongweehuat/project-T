<?php

require_once '/var/www/utils/Logger.php';

class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Synchronize all trades in the trades_open table for a given account.
     * Implements "Found Update, New Insert, Not Found Delete".
     */
    public function syncTrades($accountId, $trades) {
        try {
            $this->db->beginTransaction();

            // Parse all trade data and collect tickets for batch operations
            $parsedTrades = [];
            $tickets = [];
            foreach ($trades as $tradeString) {
                parse_str(str_replace('|', '&', $tradeString), $tradeData);
                $parsedTrades[] = $tradeData;
                $tickets[] = $tradeData['ticket'] ?? null;
            }

            // Insert or update trades in a batch
            foreach ($parsedTrades as $trade) {
                $this->upsertTrade($accountId, $trade);
            }

            // Remove trades that are not in the current batch
            $this->deleteMissingTrades($accountId, $tickets);

            // Sync trades_group and trades_config after processing the trades
            $this->syncTradesGroup($accountId);
            $this->syncTradesConfig($accountId);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            logMessage("Error syncing trades: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert or update a single trade.
     */
    private function upsertTrade($accountId, $trade) {
        
        $query = "
            INSERT INTO trades_open (
                account_id, ticket, pair, order_type, volume, profit,
                open_price, bid_price, ask_price, current_price, commission, comment,
                open_time, magic_number, last_update
            ) VALUES (
                :account_id, :ticket, :pair, :order_type, :volume, :profit,
                :open_price, :bid_price, :ask_price, :current_price, :commission, :comment,
                :open_time, :magic_number, NOW()
            )
            ON DUPLICATE KEY UPDATE
                pair = VALUES(pair),
                order_type = VALUES(order_type),
                volume = VALUES(volume),
                profit = VALUES(profit),
                open_price = VALUES(open_price),
                bid_price = VALUES(bid_price),
                ask_price = VALUES(ask_price),
                current_price = VALUES(current_price),
                commission = VALUES(commission),
                comment = VALUES(comment),
                open_time = VALUES(open_time),
                magic_number = VALUES(magic_number),
                last_update = NOW()
        ";
    
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':account_id' => $accountId,
            ':ticket' => $trade['ticket'],
            ':pair' => $trade['pair'],
            ':order_type' => $trade['order_type'],
            ':volume' => $trade['volume'],
            ':profit' => $trade['profit'],
            ':open_price' => $trade['open_price'],
            ':bid_price' => $trade['bid_price'],
            ':ask_price' => $trade['ask_price'],
            ':current_price' => $trade['current_price'], 
            ':commission' => $trade['commission'],
            ':comment' => $trade['comment'],
            ':open_time' => $trade['open_time'],
            ':magic_number' => $trade['magic_number'],
        ]);
    }
    

    /**
     * Delete trades that are no longer open.
     */
    private function deleteMissingTrades($accountId, $tickets) {
        if (empty($tickets)) {
            return;
        }

        $placeholders = rtrim(str_repeat('?,', count($tickets)), ',');
        $query = "
            DELETE FROM trades_open
            WHERE account_id = ? AND ticket NOT IN ($placeholders)
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute(array_merge([$accountId], $tickets));
    }

    /**
     * Synchronize trades_group without using group_id, relying on composite keys.
     */
    public function syncTradesGroup($accountId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM trades_group WHERE account_id = ?");
            $stmt->execute([$accountId]);

            $stmtInsert = $this->db->prepare("INSERT INTO trades_group (
                account_id, magic_number, pair, order_type, total_volume,
                weighted_open_price, profit, last_update
            ) SELECT
                account_id, magic_number, pair, order_type,
                SUM(volume) AS total_volume,
                SUM(open_price * volume) / SUM(volume) AS weighted_open_price,
                SUM(profit) AS profit,
                NOW()
            FROM trades_open
            WHERE account_id = ?
            GROUP BY account_id, magic_number, pair, order_type");

            $stmtInsert->execute([$accountId]);
            return true;
        } catch (PDOException $e) {
            logMessage("Error syncing trades_group: " . $e->getMessage());
            return false;
        }
    }
    


    /**
     * Synchronize trades_config without using group_id.
     */
    public function syncTradesConfig($accountId) {
        try {
            $stmt = $this->db->prepare("INSERT INTO trades_config (
                account_id, magic_number, pair, order_type, stop_loss, take_profit, remark, last_update
            ) SELECT
                account_id, magic_number, pair, order_type,
                NULL, NULL, '', NOW()
            FROM trades_group
            WHERE account_id = ?
            ON DUPLICATE KEY UPDATE last_update = VALUES(last_update)");

            $stmt->execute([$accountId]);
            return true;
        } catch (PDOException $e) {
            logMessage("Error syncing trades_config: " . $e->getMessage());
            return false;
        }
    }
    

    public function getGroupedTrades($accountId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                tg.id AS group_id,
                tg.account_id,
                tg.magic_number,
                tg.pair,
                tg.order_type,
                tg.total_volume,
                tg.weighted_open_price,
                tg.profit,
                (
                    SELECT tp.current_price 
                    FROM trades_open tp
                    WHERE tp.account_id = tg.account_id
                    AND tp.magic_number = tg.magic_number
                    AND tp.pair = tg.pair
                    AND tp.order_type = tg.order_type
                    LIMIT 1
                ) AS current_price, 
                (
                    CASE
                        WHEN tg.order_type = 'buy' THEN (
                            SELECT MIN(tp.open_price)
                            FROM trades_open tp
                            WHERE tp.account_id = tg.account_id
                            AND tp.magic_number = tg.magic_number
                            AND tp.pair = tg.pair
                            AND tp.order_type = 'buy'
                        )
                        WHEN tg.order_type = 'sell' THEN (
                            SELECT MAX(tp.open_price)
                            FROM trades_open tp
                            WHERE tp.account_id = tg.account_id
                            AND tp.magic_number = tg.magic_number
                            AND tp.pair = tg.pair
                            AND tp.order_type = 'sell'
                        )
                        ELSE NULL
                    END
                ) AS extreme_price,
                (
                    SELECT pc.point_value                     
                    FROM pair_config pc
                    WHERE pc.pair = tg.pair
                    LIMIT 1
                ) AS point_value,  
                tc.stop_loss,                              -- Add stop_loss from trades_config
                tc.take_profit,                            -- Add take_profit from trades_config
                tg.last_update
            FROM trades_group tg
            LEFT JOIN trades_config tc
            ON tg.account_id = tc.account_id
            AND tg.magic_number = tc.magic_number
            AND tg.pair = tc.pair
            AND tg.order_type = tc.order_type
            WHERE tg.account_id = :account_id
            ORDER BY tg.magic_number,tg.pair,tg.order_type
        ");
        $stmt->execute([':account_id' => $accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    public function getTradesByGroupId($groupId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                pair,
                order_type,
                volume,
                open_price,
                bid_price,       -- Include bid_price
                ask_price,       -- Include ask_price
                current_price,
                profit,
                last_update
            FROM trades_open
            WHERE group_id = :group_id
        ");
        $stmt->execute([':group_id' => $groupId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    

}
