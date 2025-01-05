<?php

require_once '/var/www/utils/Logger.php';

class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Synchronize trades_open with the latest data.
     * Implements "Found Update, New Insert, Not Found Delete" approach.
     */
    public function syncTradesOpen($accountId, $trades) {
        try {
            $this->db->beginTransaction();

            // Fetch existing tickets for the account
            $stmt = $this->db->prepare("SELECT ticket FROM trades_open WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            $existingTickets = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            $newTickets = array_column($trades, 'ticket');
            $toDelete = array_diff($existingTickets, $newTickets);

            // Delete trades not found in the new data
            if (!empty($toDelete)) {
                $stmt = $this->db->prepare("DELETE FROM trades_open WHERE ticket IN (" . implode(',', array_map('intval', $toDelete)) . ")");
                $stmt->execute();
                logMessage("Deleted trades: " . implode(',', $toDelete));
            }

            // Insert or update trades
            $stmtInsert = $this->db->prepare("
                INSERT INTO trades_open (ticket, account_id, magic_number, pair, order_type, volume, open_price, profit, open_time, bid_price, ask_price, last_update)
                VALUES (:ticket, :account_id, :magic_number, :pair, :order_type, :volume, :open_price, :profit, :open_time, :bid_price, :ask_price, NOW())
                ON DUPLICATE KEY UPDATE
                    magic_number = VALUES(magic_number),
                    pair = VALUES(pair),
                    order_type = VALUES(order_type),
                    volume = VALUES(volume),
                    open_price = VALUES(open_price),
                    profit = VALUES(profit),
                    bid_price = VALUES(bid_price),
                    ask_price = VALUES(ask_price),
                    last_update = NOW()
            ");

            foreach ($trades as $trade) {
                $stmtInsert->execute([
                    ':ticket'       => $trade['ticket'],
                    ':account_id'   => $accountId,
                    ':magic_number' => $trade['magic_number'],
                    ':pair'         => $trade['pair'],
                    ':order_type'   => $trade['order_type'],
                    ':volume'       => $trade['volume'],
                    ':open_price'   => $trade['open_price'],
                    ':profit'       => $trade['profit'],
                    ':open_time'    => $trade['open_time'],
                    ':bid_price'    => $trade['bid_price'],
                    ':ask_price'    => $trade['ask_price']
                ]);
                logMessage("Processed trade: " . json_encode($trade));
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            logMessage("Error syncing trades_open: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronize trades_group with the latest grouped data from trades_open.
     * Implements "Found Update, New Insert, Not Found Delete" approach.
     */
    public function syncTradesGroup($accountId) {
        try {
            // Fetch existing grouped trades
            $stmt = $this->db->prepare("SELECT CONCAT_WS('-', magic_number, pair, order_type) AS unique_key FROM trades_group WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            $existingGroups = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Calculate new grouped data from trades_open
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
     * Synchronize trades_config with trades_group.
     */
    public function syncTradesConfig($accountId) {
        try {
            $this->db->beginTransaction();

            // Fetch grouped trades
            $stmt = $this->db->prepare("SELECT * FROM trades_group WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sync configs
            foreach ($groups as $group) {
                $stmtConfig = $this->db->prepare("
                    INSERT INTO trades_config (account_id, magic_number, pair, order_type, stop_loss, take_profit, remarks, last_update)
                    VALUES (:account_id, :magic_number, :pair, :order_type, NULL, NULL, '', NOW())
                    ON DUPLICATE KEY UPDATE
                        last_update = NOW()
                ");
                $stmtConfig->execute([
                    ':account_id'   => $group['account_id'],
                    ':magic_number' => $group['magic_number'],
                    ':pair'         => $group['pair'],
                    ':order_type'   => $group['order_type']
                ]);
                logMessage("Synced config for group: " . json_encode($group));
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            logMessage("Error syncing trades_config: " . $e->getMessage());
            return false;
        }
    }
}

