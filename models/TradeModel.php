<?php
class TradeModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
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
            error_log("Error deleting trades_group: " . $e->getMessage());
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
                    account_id, magic_number, pair, order_type, total_volume, weighted_open_price, profit, last_update
                )
                SELECT 
                    account_id,
                    magic_number,
                    pair,
                    order_type,
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
            error_log("Error grouping trades: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync trades_group with trades_config for the given account_id.
     */
    public function syncWithTradesConfig($accountId) {
        try {
            // Fetch all trades_group records for the account_id
            $stmt = $this->db->prepare("
                SELECT account_id, magic_number, pair, order_type FROM trades_group WHERE account_id = :account_id
            ");
            $stmt->execute([':account_id' => $accountId]);
            $tradesGroup = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all trades_config records for the account_id
            $stmt = $this->db->prepare("
                SELECT account_id, magic_number, pair, order_type FROM trades_config WHERE account_id = :account_id
            ");
            $stmt->execute([':account_id' => $accountId]);
            $tradesConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert trades_config to a lookup array
            $configLookup = [];
            foreach ($tradesConfig as $config) {
                $key = "{$config['account_id']}_{$config['magic_number']}_{$config['pair']}_{$config['order_type']}";
                $configLookup[$key] = $config;
            }

            // Sync logic
            foreach ($tradesGroup as $group) {
                $key = "{$group['account_id']}_{$group['magic_number']}_{$group['pair']}_{$group['order_type']}";

                if (!isset($configLookup[$key])) {
                    // Insert new config if not exists
                    $this->insertDefaultConfig($group);
                } else {
                    // Update config if needed
                    $this->updateConfig($group, $configLookup[$key]);
                }

                // Remove from lookup to track stale configs
                unset($configLookup[$key]);
            }

            // Remove stale configs
            foreach ($configLookup as $staleConfig) {
                $this->archiveConfig($staleConfig);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error syncing with trades_config: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert default config for a new trades_group entry.
     */
    private function insertDefaultConfig($group) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO trades_config (account_id, magic_number, pair, order_type, stop_loss, take_profit, remarks, last_update)
                VALUES (:account_id, :magic_number, :pair, :order_type, NULL, NULL, 'Default configuration', NOW())
            ");
            $stmt->execute([
                ':account_id'   => $group['account_id'],
                ':magic_number' => $group['magic_number'],
                ':pair'         => $group['pair'],
                ':order_type'   => $group['order_type'],
            ]);
        } catch (PDOException $e) {
            error_log("Error inserting default config: " . $e->getMessage());
        }
    }

    /**
     * Update existing config for a trades_group entry.
     */
    private function updateConfig($group, $config) {
        try {
            // Update logic here if needed, e.g., adjusting stop_loss or take_profit
        } catch (PDOException $e) {
            error_log("Error updating config: " . $e->getMessage());
        }
    }

    /**
     * Archive stale trades_config entry.
     */
    private function archiveConfig($config) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM trades_config WHERE account_id = :account_id AND magic_number = :magic_number AND pair = :pair AND order_type = :order_type
            ");
            $stmt->execute([
                ':account_id'   => $config['account_id'],
                ':magic_number' => $config['magic_number'],
                ':pair'         => $config['pair'],
                ':order_type'   => $config['order_type'],
            ]);
        } catch (PDOException $e) {
            error_log("Error archiving config: " . $e->getMessage());
        }
    }
}
?>
