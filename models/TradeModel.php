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
        // Determine the current price based on the order type
        $currentPrice = null;
        if ($trade['order_type'] === 'buy') {
            $currentPrice = $trade['bid_price']; // Use Bid price for Buy trades
        } elseif ($trade['order_type'] === 'sell') {
            $currentPrice = $trade['ask_price']; // Use Ask price for Sell trades
        }
    
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
            ':current_price' => $currentPrice, // Pass the calculated current price
            ':commission' => $trade['commission'], // New field
            ':comment' => $trade['comment'],       // New field
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

    public function syncTradesGroup($accountId) {
        try {
            // Step 1: Fetch existing group keys
            $stmt = $this->db->prepare("
                SELECT id, CONCAT_WS('-', magic_number, pair, order_type) AS unique_key
                FROM trades_group
                WHERE account_id = :account_id
            ");
            $stmt->execute([':account_id' => $accountId]);
            $existingGroups = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
            logMessage("Existing groups: " . json_encode($existingGroups));
    
            // Step 2: Recalculate group data from trades_open
            $stmt = $this->db->prepare("
                SELECT 
                    account_id,
                    magic_number,
                    pair,
                    order_type,
                    SUM(volume) AS total_volume,
                    SUM(open_price * volume) / SUM(volume) AS weighted_open_price,
                    SUM(profit) AS profit
                FROM trades_open
                WHERE account_id = :account_id
                GROUP BY magic_number, pair, order_type
            ");
            $stmt->execute([':account_id' => $accountId]);
            $newGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            logMessage("Recalculated groups: " . json_encode($newGroups));
    
            // Step 3: Update trades_group table
            $stmtInsert = $this->db->prepare("
                INSERT INTO trades_group (
                    account_id, magic_number, pair, order_type, total_volume, 
                    weighted_open_price, profit, last_update
                ) VALUES (
                    :account_id, :magic_number, :pair, :order_type, :total_volume,
                    :weighted_open_price, :profit, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    total_volume = VALUES(total_volume),
                    weighted_open_price = VALUES(weighted_open_price),
                    profit = VALUES(profit),
                    last_update = NOW()
            ");
    
            foreach ($newGroups as $group) {
                $stmtInsert->execute([
                    ':account_id' => $group['account_id'],
                    ':magic_number' => $group['magic_number'],
                    ':pair' => $group['pair'],
                    ':order_type' => $group['order_type'],
                    ':total_volume' => $group['total_volume'],
                    ':weighted_open_price' => $group['weighted_open_price'],
                    ':profit' => $group['profit']
                ]);
            }
    
            // Step 4: Fetch updated group IDs
            $stmt = $this->db->prepare("
                SELECT id, CONCAT_WS('-', magic_number, pair, order_type) AS unique_key
                FROM trades_group
                WHERE account_id = :account_id
            ");
            $stmt->execute([':account_id' => $accountId]);
            $updatedGroups = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
            logMessage("Updated groups: " . json_encode($updatedGroups));
    
            // Step 5: Update group_id in trades_open
            $stmtUpdateGroupId = $this->db->prepare("
                UPDATE trades_open
                SET group_id = (
                    SELECT id FROM trades_group
                    WHERE trades_group.account_id = trades_open.account_id
                    AND trades_group.magic_number = trades_open.magic_number
                    AND trades_group.pair = trades_open.pair
                    AND trades_group.order_type = trades_open.order_type
                )
                WHERE account_id = :account_id
            ");
            $stmtUpdateGroupId->execute([':account_id' => $accountId]);
    
            // Step 6: Cleanup trades_group for pairs no longer in trades_open
            $stmtCleanup = $this->db->prepare("
                DELETE FROM trades_group
                WHERE account_id = :account_id
                AND NOT EXISTS (
                    SELECT 1 
                    FROM trades_open 
                    WHERE trades_group.account_id = trades_open.account_id
                    AND trades_group.magic_number = trades_open.magic_number
                    AND trades_group.pair = trades_open.pair
                    AND trades_group.order_type = trades_open.order_type
                )
            ");
            $stmtCleanup->execute([':account_id' => $accountId]);
    
            logMessage("Trades_group synchronized successfully for account_id: $accountId");
    
            return true;
        } catch (PDOException $e) {
            logMessage("Error syncing trades_group: " . $e->getMessage());
            return false;
        }
    }
    


    /**
     * Synchronize trades_config table with the latest trades_group data.
     */
    public function syncTradesConfig($accountId) {
        try {
            // Insert or update trades_config with group_id
            $stmt = $this->db->prepare("
                INSERT INTO trades_config (
                    group_id, account_id, magic_number, pair, order_type, 
                    stop_loss, take_profit, remarks, last_update
                ) 
                SELECT 
                    tg.id AS group_id,                -- Fetch group_id from trades_group
                    tg.account_id, 
                    tg.magic_number, 
                    tg.pair, 
                    tg.order_type, 
                    NULL AS stop_loss,                -- Default NULL for stop_loss
                    NULL AS take_profit,              -- Default NULL for take_profit
                    '' AS remarks,                    -- Default empty string for remarks
                    NOW() AS last_update
                FROM trades_group tg
                WHERE tg.account_id = :account_id
                ON DUPLICATE KEY UPDATE
                    last_update = VALUES(last_update)
            ");
            $stmt->execute([':account_id' => $accountId]);
            
            logMessage("Trades_config synced successfully for account_id: $accountId");
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
