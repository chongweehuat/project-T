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

    public function getConfigsWithGroup($accountId)
    {
        $query = "
            SELECT 
                tc.id AS config_id,
                tg.account_id,
                tg.magic_number,
                tg.pair,
                tg.order_type,
                tc.stop_loss,
                tc.take_profit,
                tc.remark,
                tc.auth_FT,
                tc.auth_AT,
                tc.auth_CP,
                tc.auth_SL,
                tc.auth_CL,
                tg.id AS group_id,
                tg.total_volume,
                tg.weighted_open_price,
                COALESCE(tg.weighted_stop_loss, tc.stop_loss) AS effective_stop_loss,
                tg.weighted_take_profit,
                tg.profit,
                tg.last_update,
                pc.point_value
            FROM trades_group tg
            LEFT JOIN trades_config tc ON tg.id = tc.group_id
            LEFT JOIN pair_config pc ON tg.pair = pc.pair
            WHERE tg.account_id = ?
        ";
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$accountId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching configurations with groups: " . $e->getMessage());
        }
    }

    public function getConfigsWithoutGroup($accountId)
    {
        $query = "
            SELECT 
                tc.id AS config_id,
                tc.account_id,
                tc.magic_number,
                tc.pair,
                tc.order_type,
                tc.stop_loss,
                tc.take_profit,
                tc.remark,
                tc.auth_FT,
                tc.auth_AT,
                tc.auth_CP,
                tc.auth_SL,
                tc.auth_CL,
                tc.last_update,
                pc.point_value,
                NULL AS group_id,
                NULL AS total_volume,
                NULL AS weighted_open_price,
                NULL AS weighted_stop_loss,
                NULL AS weighted_take_profit,
                NULL AS profit
            FROM trades_config tc
            LEFT JOIN pair_config pc ON tc.pair = pc.pair
            WHERE tc.account_id = ? AND tc.group_id IS NULL
        ";
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$accountId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error fetching configurations without groups: " . $e->getMessage());
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
