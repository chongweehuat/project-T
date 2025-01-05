<?php

class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Insert a single trade into the trades_open table.
     */
    public function insertTrade($trade) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO trades_open (
                    ticket, account_id, magic_number, pair, order_type, volume,
                    open_price, profit, open_time, bid_price, ask_price, last_update
                ) VALUES (
                    :ticket, :account_id, :magic_number, :pair, :order_type, :volume,
                    :open_price, :profit, :open_time, :bid_price, :ask_price, NOW()
                )
            ");
            $stmt->execute([
                ':ticket'       => $trade['ticket'],
                ':account_id'   => $trade['account_id'],
                ':magic_number' => $trade['magic_number'],
                ':pair'         => $trade['pair'],
                ':order_type'   => $trade['order_type'],
                ':volume'       => $trade['volume'],
                ':open_price'   => $trade['open_price'],
                ':profit'       => $trade['profit'],
                ':open_time'    => $trade['open_time'],
                ':bid_price'    => $trade['bid_price'],
                ':ask_price'    => $trade['ask_price'],
            ]);
            return true;
        } catch (PDOException $e) {
            logMessage("Insert error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all trades_open records for the account_id.
     */
    public function deleteTradesOpenByAccountId($accountId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM trades_open WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            return true;
        } catch (PDOException $e) {
            logMessage("Error deleting trades_open: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all trades_group records for the account_id.
     */
    public function deleteTradesGroupByAccountId($accountId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM trades_group WHERE account_id = :account_id");
            $stmt->execute([':account_id' => $accountId]);
            return true;
        } catch (PDOException $e) {
            logMessage("Error deleting trades_group: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Group trades from trades_open into trades_group for the given account_id.
     */
    public function groupTradesForAccount($accountId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO trades_group (
                    account_id, magic_number, pair, order_type, total_volume,
                    weighted_open_price, profit, last_update
                )
                SELECT
                    account_id, magic_number, pair, order_type,
                    SUM(volume) AS total_volume,
                    SUM(open_price * volume) / SUM(volume) AS weighted_open_price,
                    SUM(profit) AS profit,
                    NOW() AS last_update
                FROM trades_open
                WHERE account_id = :account_id
                GROUP BY account_id, magic_number, pair, order_type
            ");
            $stmt->execute([':account_id' => $accountId]);
            return true;
        } catch (PDOException $e) {
            logMessage("Error grouping trades: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Syncs trades_config based on trades_group data.
     */
    public function syncTradesConfig($accountId) {
        try {
            // Fetch grouped trades
            $query = "SELECT id, account_id, magic_number, pair, order_type, total_volume, weighted_open_price, profit 
                    FROM trades_group 
                    WHERE account_id = :account_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':account_id' => $accountId]);
            $groupedTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($groupedTrades as $group) {
                // Check if configuration exists for this group
                $query = "SELECT id FROM trades_config 
                        WHERE account_id = :account_id 
                        AND magic_number = :magic_number 
                        AND pair = :pair 
                        AND order_type = :order_type";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':account_id' => $group['account_id'],
                    ':magic_number' => $group['magic_number'],
                    ':pair' => $group['pair'],
                    ':order_type' => $group['order_type']
                ]);

                $config = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($config) {
                    // Update the existing configuration
                    $this->updateConfig($group, $config);
                } else {
                    // Insert a new default configuration
                    $this->insertDefaultConfig($group);
                }
            }

            return true;
        } catch (PDOException $e) {
            logMessage("Error syncing trades_config: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Insert a default config for a trades_group entry.
     */
    private function insertDefaultConfig($group) {
        try {
            $query = "INSERT INTO trades_config 
                    (account_id, magic_number, pair, order_type, stop_loss, take_profit, remarks, last_update)
                    VALUES (:account_id, :magic_number, :pair, :order_type, :stop_loss, :take_profit, :remarks, CURRENT_TIMESTAMP)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':account_id' => $group['account_id'],
                ':magic_number' => $group['magic_number'],
                ':pair' => $group['pair'],
                ':order_type' => $group['order_type'],
                ':stop_loss' => null, // Default stop_loss (adjust logic if needed)
                ':take_profit' => null, // Default take_profit (adjust logic if needed)
                ':remarks' => ''
            ]);
        } catch (PDOException $e) {
            logMessage("Error inserting default config for group_id={$group['id']}: " . $e->getMessage());
        }
    }


    /**
     * Update an existing config for a trades_group entry.
     */
    private function updateConfig($group, $config) {
        try {
            $query = "UPDATE trades_config 
                    SET stop_loss = :stop_loss, 
                        take_profit = :take_profit, 
                        remarks = :remarks, 
                        last_update = CURRENT_TIMESTAMP 
                    WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':stop_loss' => $config['stop_loss'], // Replace with calculated stop_loss if needed
                ':take_profit' => $config['take_profit'], // Replace with calculated take_profit if needed
                ':remarks' => $config['remarks'] ?? '',
                ':id' => $config['id']
            ]);
        } catch (PDOException $e) {
            logMessage("Error updating config for group_id={$group['id']}: " . $e->getMessage());
        }
    }

}
?>
