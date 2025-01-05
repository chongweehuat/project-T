<?php

require_once '/var/www/utils/Logger.php'; 
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';

function cleanPostData($data) {
    $cleanedData = [];
    foreach ($data as $key => $value) {
        $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value; // Remove trailing null bytes
        $cleanedData[$key] = $cleanedValue;
    }
    return $cleanedData;
}

// Clean the incoming POST data
$_POST = cleanPostData($_POST);

// Validate required fields
$requiredFields = [
    'ea_version', 'account_id', 'ticket', 'pair', 'order_type',
    'volume', 'open_price', 'profit', 'open_time', 'bid_price', 'ask_price', 'magic_number'
];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field])) {
        logMessage("Missing required field: {$field}");
        http_response_code(400); // Bad Request
        exit; // No response body
    }
}

// Extract trade data
$trade = [
    'ea_version'   => $_POST['ea_version'],
    'account_id'   => intval($_POST['account_id']),
    'ticket'       => intval($_POST['ticket']),
    'pair'         => $_POST['pair'],
    'order_type'   => $_POST['order_type'],
    'volume'       => floatval($_POST['volume']),
    'open_price'   => floatval($_POST['open_price']),
    'profit'       => floatval($_POST['profit']),
    'open_time'    => $_POST['open_time'], // Ensure proper format on insertion
    'bid_price'    => floatval($_POST['bid_price']),
    'ask_price'    => floatval($_POST['ask_price']),
    'magic_number' => intval($_POST['magic_number']),
];

try {
    
    // Connect to the database
    $db = Database::connect('trade');
       
    $tradeModel = new TradeModel($db);
    
    // Delete all open trades for the given account_id
    if ($tradeModel->deleteTradesOpenByAccountId($trade['account_id'])) {
        logMessage("Deleted existing trades for account_id={$trade['account_id']}");
    } else {
        logMessage("Failed to delete trades for account_id={$trade['account_id']}");
    }

    // Insert the new trade
    if ($tradeModel->insertTrade($trade)) {
        logMessage("Inserted trade: " . json_encode($trade));
        http_response_code(200); // Success
    } else {
        logMessage("Failed to insert trade: " . json_encode($trade));
        http_response_code(500); // Internal Server Error
    }
} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    exit; // No response body
}
?>
