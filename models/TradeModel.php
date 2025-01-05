<?php

require_once '/var/www/utils/Logger.php';

class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Synchronize a single trade into the trades_open table.
     * Implements "Found Update, New Insert".
     */
    public function syncSingleTrade($accountId, $trade) {
        try {
            // Check if the trade already exists
            $stmt = $this->db->prepare("SELECT id FROM trades_open WHERE account_id = :account_id AND ticket = :ticket");
            $stmt->execute([
                ':account_id' => $accountId,
                ':ticket' => $trade['ticket']
            ]);
            $existingTrade = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingTrade) {
                // Update the existing trade
                $stmt = $this->db->prepare("
                    UPDATE trades_open 
                    SET 
                        pair = :pair,
                        order_type = :order_type,
                        volume = :volume,
                        open_price = :open_price,
                        profit = :profit,
                        open_time = :open_time,
                        bid_price = :bid_price,
                        ask_price = :ask_price,
                        magic_number = :magic_number,
                        last_update = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':pair' => $trade['pair'],
                    ':order_type' => $trade['order_type'],
                    ':volume' => $trade['volume'],
                    ':open_price' => $trade['open_price'],
                    ':profit' => $trade['profit'],
                    ':open_time' => $trade['open_time'],
                    ':bid_price' => $trade['bid_price'],
                    ':ask_price' => $trade['ask_price'],
                    ':magic_number' => $trade['magic_number'],
                    ':id' => $existingTrade['id']
                ]);
                logMessage("Updated trade: " . json_encode($trade));
            } else {
                // Insert a new trade
                $stmt = $this->db->prepare("
                    INSERT INTO trades_open (account_id, ticket, pair, order_type, volume, open_price, profit, open_time, bid_price, ask_price, magic_number, last_update)
                    VALUES (:account_id, :ticket, :pair, :order_type, :volume, :open_price, :profit, :open_time, :bid_price, :ask_price, :magic_number, NOW())
                ");
                $stmt->execute([
                    ':account_id' => $accountId,
                    ':ticket' => $trade['ticket'],
                    ':pair' => $trade['pair'],
                    ':order_type' => $trade['order_type'],
                    ':volume' => $trade['volume'],
                    ':open_price' => $trade['open_price'],
                    ':profit' => $trade['profit'],
                    ':open_time' => $trade['open_time'],
                    ':bid_price' => $trade['bid_price'],
                    ':ask_price' => $trade['ask_price'],
                    ':magic_number' => $trade['magic_number']
                ]);
                logMessage("Inserted new trade: " . json_encode($trade));
            }

            // Sync trades_group and trades_config after processing the trade
            $this->syncTradesGroup($accountId);
            $this->syncTradesConfig($accountId);

            return true;

        } catch (PDOException $e) {
            logMessage("Error syncing trade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronize trades_group table with the latest grouped data.
     * Implements "Found Update, New Insert, Not Found Delete".
     */
    public function syncTradesGroup($accountId) {
        try {
            // Fetch existing group keys
            $stmt = $this->db->prepare("SELECT CONCAT_WS('-', magic_number, pair, order_type) AS unique_key FROM trades_group WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            $existingGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Calculate new group data from trades_open
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

            // Determine groups to delete
            $newKeys = array_map(function ($group) {
                return $group['magic_number'] . '-' . $group['pair'] . '-' . $group['order_type'];
            }, $newGroups);

            $toDelete = array_diff($existingGroups, $newKeys);

            // Delete obsolete groups
            if (!empty($toDelete)) {
                $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                $deleteStmt = $this->db->prepare("DELETE FROM trades_group WHERE account_id = ? AND CONCAT_WS('-', magic_number, pair, order_type) IN ($placeholders)");
                $deleteStmt->execute(array_merge([$accountId], $toDelete));
                logMessage("Deleted obsolete trades_group entries: " . implode(',', $toDelete));
            }

            // Insert or update grouped trades
            $stmtInsert = $this->db->prepare("
                INSERT INTO trades_group (account_id, magic_number, pair, order_type, total_volume, weighted_open_price, profit, last_update)
                VALUES (:account_id, :magic_number, :pair, :order_type, :total_volume, :weighted_open_price, :profit, NOW())
                ON DUPLICATE KEY UPDATE
                    total_volume = VALUES(total_volume),
                    weighted_open_price = VALUES(weighted_open_price),
                    profit = VALUES(profit),
                    last_update = NOW()
            ");

            foreach ($newGroups as $group) {
                $stmtInsert->execute([
                    ':account_id'         => $group['account_id'],
                    ':magic_number'       => $group['magic_number'],
                    ':pair'               => $group['pair'],
                    ':order_type'         => $group['order_type'],
                    ':total_volume'       => $group['total_volume'],
                    ':weighted_open_price' => $group['weighted_open_price'],
                    ':profit'             => $group['profit']
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
            // Insert or update trades_config entries based on trades_group
            $stmt = $this->db->prepare("
                INSERT INTO trades_config (account_id, magic_number, pair, order_type, stop_loss, take_profit, remarks, last_update)
                SELECT tg.account_id, tg.magic_number, tg.pair, tg.order_type, NULL, NULL, '', NOW()
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
