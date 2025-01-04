<?php

require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Function to clean the $_POST data
function cleanPostData($postData) {
    $cleanedData = [];
    foreach ($postData as $key => $value) {
        $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value; // Remove trailing null bytes
        $cleanedData[$key] = $cleanedValue;
    }
    return $cleanedData;
}

// Clean the incoming POST data
$_POST = cleanPostData($_POST);

// Extract and sanitize POST parameters
$data = [
    'ea_version' => $_POST['ea_version'] ?? null,
    'account_number' => $_POST['account_number'] ?? null,
    'symbol' => $_POST['symbol'] ?? null,
    'type' => $_POST['type'] ?? null,
    'volume' => isset($_POST['volume']) ? floatval($_POST['volume']) : null,
    'profit' => isset($_POST['profit']) ? floatval($_POST['profit']) : null,
    'open_price' => isset($_POST['open_price']) ? floatval($_POST['open_price']) : null,
    'magic_number' => isset($_POST['magic_number']) ? intval($_POST['magic_number']) : null,
    'bid_price' => isset($_POST['bid_price']) ? floatval($_POST['bid_price']) : null,
    'ask_price' => isset($_POST['ask_price']) ? floatval($_POST['ask_price']) : null,
];

// Validate required data
if (!$data['account_number'] || !$data['symbol'] || !$data['type'] || !isset($data['magic_number'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Invalid or missing trade data."]);
    exit;
}

try {
    // Connect to the database
    $db = Database::connect('trade');
    $tradeModel = new TradeModel($db);

    // Fetch all trades for the account
    $existingTrades = $tradeModel->getTradesByAccount($data['account_number']);

    // Group trades by symbol, order type, and magic number
    $groupedTrades = [];
    foreach ($existingTrades as $trade) {
        $key = "{$trade['pair']}|{$trade['order_type']}|{$trade['magic_number']}";

        if (!isset($groupedTrades[$key])) {
            $groupedTrades[$key] = [
                'symbol' => $trade['pair'],
                'type' => $trade['order_type'],
                'magic_number' => $trade['magic_number'],
                'total_volume' => 0,
                'total_profit' => 0,
                'volume_price_product' => 0, // For prorated open price
                'trade_count' => 0,
                'currency_price' => 0, // Bid or Ask price
            ];
        }

        // Aggregate data
        $groupedTrades[$key]['total_volume'] += $trade['volume'];
        $groupedTrades[$key]['total_profit'] += $trade['profit'];
        $groupedTrades[$key]['volume_price_product'] += $trade['volume'] * $trade['open_price'];
        $groupedTrades[$key]['trade_count'] += 1;

        // Determine Bid/Ask price based on order type
        if ($trade['order_type'] === 'buy') {
            $groupedTrades[$key]['currency_price'] = $trade['ask_price'];
        } elseif ($trade['order_type'] === 'sell') {
            $groupedTrades[$key]['currency_price'] = $trade['bid_price'];
        }
    }

    // Finalize calculations for each group
    foreach ($groupedTrades as &$group) {
        $group['prorated_open_price'] = $group['total_volume'] > 0 
            ? $group['volume_price_product'] / $group['total_volume'] 
            : 0; // Avoid division by zero
        unset($group['volume_price_product']); // Remove temporary field
    }

    // Upsert grouped data into the database
    foreach ($groupedTrades as $group) {
        $tradeModel->upsertGroupedTrade($data['account_number'], $group);
    }

    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Trade data processed successfully."]);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(["status" => "error", "message" => "Database error."]);
    exit;
}
