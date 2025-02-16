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

    public function getAuthorizationConfig($accountId, $magicNumber, $pair, $orderType)
    {
        try {
            $query = "SELECT auth_FT, auth_AT, auth_CP, auth_SL, auth_CL 
                    FROM trades_config 
                    WHERE account_id = ? AND magic_number = ? AND pair = ? AND order_type = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$accountId, $magicNumber, $pair, $orderType]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Database error while fetching authorization configuration: " . $e->getMessage());
        }
    }

    public function updateRemark($accountId, $magicNumber, $pair, $orderType, $remark)
    {
        try {
            $query = "UPDATE trades_config
                    SET remark = ?
                    WHERE account_id = ? AND magic_number = ? AND pair = ? AND order_type = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$remark, $accountId, $magicNumber, $pair, $orderType]);

            return $stmt->rowCount() > 0; // 返回是否更新成功
        } catch (PDOException $e) {
            // 捕获异常并提供更多上下文信息
            throw new Exception("Database error while updating remark for account_id: $accountId, magic_number: $magicNumber, pair: $pair, order_type: $orderType. Error: " . $e->getMessage());
        }
    }

    public function getAllConfigs($accountId)
    {
        try {
            $query = "
                SELECT 
                    tc.id AS config_id,
                    tc.account_id,
                    tc.magic_number,
                    tc.pair,
                    tc.order_type,
                    COALESCE(tg.total_volume, 0) AS total_volume,
                    COALESCE(tg.weighted_open_price, 0) AS weighted_open_price,
                    COALESCE(tg.profit, 0) AS profit,
                    tc.stop_loss,
                    tc.take_profit,
                    tc.trigger_price,
                    tc.trailing_stop,
                    tc.extreme_price,
                    tc.remark,
                    tc.auth_FT,
                    tc.auth_AT,
                    tc.auth_CP,
                    tc.auth_SL,
                    tc.auth_CL,
                    GREATEST(COALESCE(tc.last_update, '0000-00-00 00:00:00'), COALESCE(tg.last_update, '0000-00-00 00:00:00')) AS last_update,
                    pc.point_value,
                    COALESCE(a.equity, 0) AS account_equity, 
                    CASE 
                        WHEN tg.order_type = 'sell' THEN (
                            SELECT MAX(tp.open_price)
                            FROM trades_open tp
                            WHERE tp.account_id = tg.account_id
                            AND tp.magic_number = tg.magic_number
                            AND tp.pair = tg.pair
                            AND tp.order_type = tg.order_type
                        )
                        WHEN tg.order_type = 'buy' THEN (
                            SELECT MIN(tp.open_price)
                            FROM trades_open tp
                            WHERE tp.account_id = tg.account_id
                            AND tp.magic_number = tg.magic_number
                            AND tp.pair = tg.pair
                            AND tp.order_type = tg.order_type
                        )
                        ELSE NULL
                    END AS extreme_open_price,
                    (
                        SELECT tp.current_price 
                        FROM trades_open tp
                        WHERE tp.account_id = tg.account_id
                        AND tp.magic_number = tg.magic_number
                        AND tp.pair = tg.pair
                        AND tp.order_type = tg.order_type
                        LIMIT 1
                    ) AS current_price
                FROM trades_config tc
                LEFT JOIN trades_group tg
                    ON tc.account_id = tg.account_id
                    AND tc.magic_number = tg.magic_number
                    AND tc.pair = tg.pair
                    AND tc.order_type = tg.order_type
                LEFT JOIN pair_config pc ON tc.pair = pc.pair
                LEFT JOIN accounts a ON tc.account_id = a.login
                WHERE tc.account_id = ?
                ORDER BY tc.magic_number, tc.pair, tc.order_type
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$accountId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching all configurations: " . $e->getMessage());
        }
    }



    public function updateAuthParam($id, $param, $value)
    {
        try {
            // Prepare the SQL query to update the specified authorization parameter
            $query = "UPDATE trades_config
                    SET $param = :value
                    WHERE id = :id";

            $stmt = $this->db->prepare($query);

            // Bind parameters
            $stmt->bindParam(':value', $value, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            // Execute the query
            $stmt->execute();

            // Return true if the update was successful
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // Throw an exception in case of a database error
            throw new Exception("Database error while updating authorization parameter: " . $e->getMessage());
        }
    }



    public function updateTradeConfig($groupId, $remark, $auth_FT, $auth_AT, $auth_CP, $auth_SL, $auth_CL)
    {
        $query = "UPDATE trades_config
                SET remark = ?, auth_FT = ?, auth_AT = ?, auth_CP = ?, auth_SL = ?, auth_CL = ?, last_update = NOW()
                WHERE group_id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$remark, $auth_FT, $auth_AT, $auth_CP, $auth_SL, $auth_CL, $groupId]);
    }

    public function updateStopLossTakeProfit($id, $param, $value) {
        $sql = "UPDATE trades_config SET $param = :value WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':value', $value, PDO::PARAM_STR); // Use PARAM_STR to support 5 decimal places
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function upsertTradeParam($configId, $groupId, $param, $value)
    {
        
        try {
            
            // Ensure configId is treated as null if it's the string 'null'
            if ($configId === 'null' || $configId === null || $configId === '') {
                $configId = null;
            }

            if ($configId) {
                // Update the record using config_id
                $query = "UPDATE trades_config 
                        SET $param = :value, last_update = NOW() 
                        WHERE id = :config_id";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':config_id', $configId, PDO::PARAM_INT);
            } else {
                // Insert a new record using group_id and include group_id in the values list
                $query = "INSERT INTO trades_config (account_id, magic_number, pair, order_type, group_id, $param, last_update)
                        SELECT tg.account_id, tg.magic_number, tg.pair, tg.order_type, tg.id, :value, NOW()
                        FROM trades_group tg
                        WHERE tg.id = :group_id
                        ON DUPLICATE KEY UPDATE 
                            $param = VALUES($param), 
                            last_update = NOW()";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':group_id', $groupId, PDO::PARAM_INT);
            }
            
            // Bind the value for both cases
            $stmt->bindParam(':value', $value);
            $stmt->execute();

            return $stmt->rowCount() > 0; // Return true if rows were affected
        } catch (PDOException $e) {
            throw new Exception("Database error in upsertTradeParam: " . $e->getMessage());
        }
    }



}
