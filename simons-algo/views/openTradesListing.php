<?php
require_once '/var/www/db/database.php';
require_once '/var/www/models/TradeModel.php';
require_once '/var/www/utils/Logger.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Add CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html");

// Capture the group_id from the query string
$groupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;

// Validate the group_id
if (!$groupId) {
    echo "<p style='color: red;'>Error: Missing or invalid group_id parameter.</p>";
    exit;
}

try {
    // Connect to the database
    $db = Database::connect("trade");
    $tradeModel = new TradeModel($db);

    // Fetch trades for the specified group_id
    $trades = $tradeModel->getTradesByGroupId($groupId);

    // Initialize totals
    $totalVolume = 0;
    $totalProfit = 0;

    // Render the trades
    if (!$trades || count($trades) === 0) {
        echo "<p>No trades found for group_id={$groupId}.</p>";
    } else {
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Open Trades Listing (Group ID: {$groupId})</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <style>
                .text-end { text-align: right; }
                .container { max-width: 90%; margin: 20px auto; }
                .highlight-positive { color: green; font-weight: bold; }
                .highlight-negative { color: red; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h3 class='text-center'>Open Trades Listing (Group ID: {$groupId})</h3>
                <table class='table table-striped table-bordered'>
                    <thead class='table-dark'>
                        <tr>
                            <th>#</th>
                            <th>Pair</th>
                            <th>Type</th>
                            <th class='text-end'>Volume</th>
                            <th class='text-end'>Open Price</th>
                            <th class='text-end'>Bid Price</th>
                            <th class='text-end'>Ask Price</th>
                            <th class='text-end'>Current Price</th>
                            <th class='text-end'>Profit</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>";

        foreach ($trades as $index => $trade) {
            $profitClass = $trade['profit'] >= 0 ? "highlight-positive" : "highlight-negative";
            $totalVolume += $trade['volume'];
            $totalProfit += $trade['profit'];

            echo "<tr>
                    <td>" . ($index + 1) . "</td>
                    <td>{$trade['pair']}</td>
                    <td>{$trade['order_type']}</td>
                    <td class='text-end'>{$trade['volume']}</td>
                    <td class='text-end'>{$trade['open_price']}</td>
                    <td class='text-end'>{$trade['bid_price']}</td>
                    <td class='text-end'>{$trade['ask_price']}</td>
                    <td class='text-end'>{$trade['current_price']}</td>
                    <td class='text-end {$profitClass}'>{$trade['profit']}</td>
                    <td>{$trade['last_update']}</td>
                  </tr>";
        }

        // Render totals
        echo "</tbody>
              <tfoot class='table-light'>
                  <tr>
                      <th colspan='3'>Total</th>
                      <th class='text-end'>" . number_format($totalVolume, 2) . "</th>
                      <th colspan='4'></th>
                      <th class='text-end'>" . number_format($totalProfit, 2) . "</th>
                      <th></th>
                  </tr>
              </tfoot>
              </table>
            </div>
        </body>
        </html>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error fetching trades: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
