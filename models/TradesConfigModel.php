<?php
// models/TradesConfigModel.php

class TradesConfigModel
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Store or update configuration data in the trades_config table using UPSERT.
     * Uses unique key (account_id, magic_number, pair, order_type) for UPSERT.
     *
     * @param int $accountId
     * @param int $magicNumber
     * @param string $pair
     * @param string $orderType
     * @param string|null $remark
     * @return bool
     * @throws Exception
     */
    public function storeConfig($accountId, $magicNumber, $pair, $orderType, $remark = null)
    {
        try {
            $query = "INSERT INTO trades_config (
                          account_id, magic_number, pair, order_type, 
                          remark, last_update
                      ) VALUES (
                          ?, ?, ?, ?, ?, NOW()
                      ) ON DUPLICATE KEY UPDATE
                          remark = VALUES(remark),
                          last_update = NOW();";

            // Prepare and execute query
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $accountId,
                $magicNumber,
                $pair,
                $orderType,
                $remark
            ]);

            
            return $stmt->rowCount() > 0; // Return true if rows were affected
        } catch (PDOException $e) {
            
            throw new Exception("Database error while storing or updating configuration: " . $e->getMessage());
        }
    }

    /**
     * Check if the given trade configuration is authorized.
     *
     * @param int $accountId
     * @param int $magicNumber
     * @param string $pair
     * @param string $orderType
     * @return bool
     */
    public function checkAuthorization($accountId, $magicNumber, $pair, $orderType)
    {
        try {
            $query = "SELECT is_authorized 
                      FROM trades_config 
                      WHERE account_id = ? AND magic_number = ? AND pair = ? AND order_type = ?
                      LIMIT 1";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$accountId, $magicNumber, $pair, $orderType]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && $row['is_authorized'] == 1; // Check if 'is_authorized' is true
        } catch (PDOException $e) {
            throw new Exception("Database error while checking authorization: " . $e->getMessage());
        }
    }
}
