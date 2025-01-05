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
                open_price, bid_price, ask_price, open_time, magic_number, last_update
            ) VALUES (
                :account_id, :ticket, :pair, :order_type, :volume, :profit,
                :open_price, :bid_price, :ask_price, :open_time, :magic_number, NOW()
            )
            ON DUPLICATE KEY UPDATE
                pair = VALUES(pair),
                order_type = VALUES(order_type),
                volume = VALUES(volume),
                profit = VALUES(profit),
                open_price = VALUES(open_price),
                bid_price = VALUES(bid_price),
                ask_price = VALUES(ask_price),
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
     * Synchronize trades_group table with the latest grouped data.
     */
    public function syncTradesGroup($accountId) {
        try {
            $stmt = $this->db->prepare("
                SELECT CONCAT_WS('-', magic_number, pair, order_type) AS unique_key
                FROM trades_group
                WHERE account_id = :account_id
            ");
            $stmt->execute([':account_id' => $accountId]);
            $existingGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

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

            $newKeys = array_map(function ($group) {
                return $group['magic_number'] . '-' . $group['pair'] . '-' . $group['order_type'];
            }, $newGroups);

            $toDelete = array_diff($existingGroups, $newKeys);

            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $deleteStmt = $this->db->prepare("
                    DELETE FROM trades_group 
                    WHERE account_id = ? AND CONCAT_WS('-', magic_number, pair, order_type) IN ($placeholders)
                ");
                $deleteStmt->execute(array_merge([$accountId], $toDelete));
                logMessage("Deleted obsolete trades_group entries: " . implode(',', $toDelete));
            }

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
                logMessage("Processed trades_group: " . json_encode($group));
            }

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
            $stmt = $this->db->prepare("
                INSERT INTO trades_config (
                    account_id, magic_number, pair, order_type, stop_loss, 
                    take_profit, remarks, last_update
                ) SELECT 
                    tg.account_id, tg.magic_number, tg.pair, tg.order_type, 
                    NULL, NULL, '', NOW()
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
                id,
                account_id,
                magic_number,
                pair,
                order_type,
                total_volume,
                weighted_open_price,
                profit,
                last_update
            FROM trades_group
            WHERE account_id = :account_id
        ");
        $stmt->execute([':account_id' => $accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
