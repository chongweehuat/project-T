<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable debug logging
$debugLogFile = '../logs/update-prices.log';
function logDebug($message) {
    global $debugLogFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debugLogFile, "[$timestamp] $message\n", FILE_APPEND);
}
//logDebug(var_export($_POST,1));
// Helper function to clean input values
function sanitizeInput($value) {
    // Remove null bytes and trim unnecessary whitespace
    return trim(str_replace("\0", "", $value));
}

// Retrieve and sanitize POST data
if (empty($_POST)) {
    logDebug("Error: No data received");
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

$cleanedPostData = [];
foreach ($_POST as $key => $value) {
    $cleanedPostData[sanitizeInput($key)] = sanitizeInput($value);
}

try {
    logDebug("Starting database transaction");
    $pdo->beginTransaction();

    // Process each pair in the cleaned POST data
    foreach ($cleanedPostData as $key => $value) {
        // Parse the currency pair and bid/ask values
        if (strpos($key, '_bid') !== false) {
            $pair = str_replace('_bid', '', $key);
            $bidPrice = $value;
            $askKey = $pair . '_ask';

            if (!isset($cleanedPostData[$askKey])) {
                logDebug("Error: Missing ask price for pair: $pair");
                continue;
            }

            $askPrice = $cleanedPostData[$askKey];

            logDebug("Parsed pair: $pair, bidPrice: $bidPrice, askPrice: $askPrice");

            // Update or insert into the database
            $stmt = $pdo->prepare("
                INSERT INTO currency_pairs (pair, bid_price, ask_price, last_updated)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    bid_price = VALUES(bid_price),
                    ask_price = VALUES(ask_price),
                    last_updated = NOW()
            ");
            $stmt->execute([$pair, $bidPrice, $askPrice]);
            logDebug("Database updated for pair: $pair");
        }
    }

    // Commit the transaction
    $pdo->commit();
    logDebug("Transaction committed successfully");
    echo json_encode(['status' => 'success', 'message' => 'Prices updated']);
} catch (PDOException $e) {
    $pdo->rollBack();
    logDebug("Transaction rolled back due to error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
