<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php';
$debug=true;
try {
    // Clean POST data
    function cleanPostData($data) {
        $cleanedData = [];
        foreach ($data as $key => $value) {
            $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value;
            $cleanedData[$key] = $cleanedValue;
        }
        return $cleanedData;
    }

    $_POST = cleanPostData($_POST);

    $requiredFields = [
        'ea_version', 'account_id', 'ticket', 'pair', 'order_type', 'volume', 
        'open_price', 'profit', 'open_time', 'bid_price', 'ask_price', 'magic_number'
    ];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            logMessage("Missing field: $field");
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Extract trade data
    $trade = [
        'account_id'   => intval($_POST['account_id']),
        'ticket'       => $_POST['ticket'], // Already a string (casted in EA)
        'pair'         => $_POST['pair'],
        'order_type'   => $_POST['order_type'],
        'volume'       => floatval($_POST['volume']),
        'open_price'   => floatval($_POST['open_price']),
        'profit'       => floatval($_POST['profit']),
        'open_time'    => $_POST['open_time'],
        'bid_price'    => floatval($_POST['bid_price']),
        'ask_price'    => floatval($_POST['ask_price']),
        'magic_number' => intval($_POST['magic_number']),
    ];

    // Connect to database
    $db = Database::connect('trade');
    $tradeModel = new TradeModel($db);

    // Sync trades_open
    if (!$tradeModel->syncTradesOpen($trade['account_id'], $trades)) {
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'message' => 'Failed to sync trades_open']));
    }

    // Sync trades_group
    if (!$tradeModel->syncTradesGroup($trade['account_id'])) {
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'message' => 'Failed to sync trades_group']));
    }

    // Sync trades_config
    if (!$tradeModel->syncTradesConfig($trade['account_id'])) {
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'message' => 'Failed to sync trades_config']));
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Trades synced successfully']);

} catch (Exception $e) {
    logMessage("Error in TradeInfoCollector: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Internal Server Error']));
}

