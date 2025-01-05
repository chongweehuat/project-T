<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php'; // Ensure this file contains the logMessage function

function cleanPostData($data) {
    $cleanedData = [];
    foreach ($data as $key => $value) {
        $cleanedValue = is_string($value) ? rtrim($value, "\0") : $value; // Remove trailing null bytes
        $cleanedData[$key] = $cleanedValue;
    }
    return $cleanedData;
}

// Clean and validate POST data
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

try {
    // Connect to the 'trade' database
    $db = Database::connect('trade');
    $tradeModel = new TradeModel($db);

    // Step 1: Delete existing open trades for the account
    if ($tradeModel->deleteTradesOpenByAccountId($trade['account_id'])) {
        logMessage("Deleted existing trades for account_id={$trade['account_id']}");
    } else {
        logMessage("Failed to delete trades for account_id={$trade['account_id']}");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to delete existing trades."]);
        exit;
    }

    // Step 2: Insert the new trade into trades_open
    if ($tradeModel->insertTrade($trade)) {
        logMessage("Inserted trade: " . json_encode($trade));
    } else {
        logMessage("Failed to insert trade: " . json_encode($trade));
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to insert trade."]);
        exit;
    }

    // Step 3: Delete existing trades_group entries for the account
    if ($tradeModel->deleteTradesGroupByAccountId($trade['account_id'])) {
        logMessage("Deleted trades_group entries for account_id={$trade['account_id']}");
    } else {
        logMessage("Failed to delete trades_group entries for account_id={$trade['account_id']}");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to delete trades group entries."]);
        exit;
    }

    // Step 4: Group trades into trades_group
    if ($tradeModel->groupTradesForAccount($trade['account_id'])) {
        logMessage("Grouped trades for account_id={$trade['account_id']}");
    } else {
        logMessage("Failed to group trades for account_id={$trade['account_id']}");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to group trades."]);
        exit;
    }

    // Step 5: Sync trades_group with trades_config
    if ($tradeModel->syncTradesConfig($trade['account_id'])) {
        logMessage("Synced trades_config for account_id={$trade['account_id']}");
    } else {
        logMessage("Failed to sync trades_config for account_id={$trade['account_id']}");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to sync trades config."]);
        exit;
    }

    logMessage("Trade processing completed successfully for account_id={$trade['account_id']}");
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Trade processed successfully."]);

} catch (Exception $e) {
    logMessage("Server error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error."]);
}
?>
