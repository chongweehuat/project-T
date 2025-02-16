<?php

class AccountModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAllAccounts() {
        $stmt = $this->db->query("SELECT login, account_name AS name, broker_name, balance, equity, free_margin,remark, last_update FROM accounts");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAccountByID($accountId) {
        $stmt = $this->db->query("SELECT *, account_name AS name FROM accounts where login=$accountId");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertAccount($data) {
        $now = date('Y-m-d H:i:s');

        // Check if the account already exists
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE login = :login");
        $stmt->bindParam(':login', $data['account_number'], PDO::PARAM_INT);
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            // Update existing account
            $update = $this->db->prepare("
                UPDATE accounts 
                SET account_name = :account_name,
                    broker_name = :broker_name,
                    leverage = :leverage,
                    balance = :balance,
                    equity = :equity,
                    free_margin = :free_margin,
                    open_count = :open_count,
                    total_volume = :total_volume,
                    remark = :ea_version,
                    last_update = :last_update
                WHERE login = :login
            ");
            $update->execute([
                ':account_name' => $data['account_name'],
                ':broker_name' => $data['broker_name'],
                ':leverage' => $data['leverage'],
                ':balance' => $data['balance'],
                ':equity' => $data['equity'],
                ':free_margin' => $data['free_margin'],
                ':open_count' => $data['open_count'],
                ':total_volume' => $data['total_volume'],
                ':ea_version' => $data['ea_version'],
                ':last_update' => $now,
                ':login' => $data['account_number']
            ]);
        } else {
            // Insert new account
            $insert = $this->db->prepare("
                INSERT INTO accounts 
                (login, account_name, broker_name, leverage, balance, equity, free_margin, open_count, total_volume, init_date, last_update)
                VALUES
                (:login, :account_name, :broker_name, :leverage, :balance, :equity, :free_margin, :open_count, :total_volume, :init_date, :last_update)
            ");
            $insert->execute([
                ':login' => $data['account_number'],
                ':account_name' => $data['account_name'],
                ':broker_name' => $data['broker_name'],
                ':leverage' => $data['leverage'],
                ':balance' => $data['balance'],
                ':equity' => $data['equity'],
                ':free_margin' => $data['free_margin'],
                ':open_count' => $data['open_count'],
                ':total_volume' => $data['total_volume'],
                ':init_date' => $now,
                ':last_update' => $now
            ]);
        }
    }
}
