<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Trades Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .table-container {
            max-width: 90%;
            margin: 20px auto;
        }

        .text-end {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mt-4">Open Trades for Account <?php echo htmlspecialchars($_GET['login'] ?? ''); ?></h1>
        <div class="table-container">
            <table class="table table-striped table-bordered" id="tradesTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Pair</th>
                        <th>Type</th>
                        <th class="text-end">Volume</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Open Price</th>
                        <th class="text-end">Stop Loss</th>
                        <th class="text-end">Take Profit</th>
                        <th>Open Time</th>
                        <th>Status</th>
                        <th class="text-end">Magic Number</th>
                        <th class="text-end">Weighted Open Price</th>
                        <th class="text-end">Currency Price</th>
                        <th class="text-end">Total Lot</th>
                        <th class="text-end">Total Trades</th>
                        <th class="text-end">Profit/Loss</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows will be dynamically injected here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const login = "<?php echo htmlspecialchars($_GET['login'] ?? ''); ?>"; // Account login ID
        const apiEndpoint = `https://sapi.my369.click/getTrades.php?login=${login}`;

        // Function to fetch trades data and update the table
        async function fetchAndUpdateTrades() {
        try {
            const response = await axios.get(apiEndpoint);
            const tradesData = response.data;

            if (tradesData.status === "success" && tradesData.data) {
                const trades = tradesData.data;
                const tableBody = document.querySelector("#tradesTable tbody");

                // Clear existing table rows
                tableBody.innerHTML = "";

                // Group trades by (Pair, Type, Magic Number)
                const groupedTrades = {};
                let totalLot = 0; // Correct Total Lot (sum of all trade volumes)
                let totalTradesCount = 0; // Correct Total Trades (count of all trades)
                let totalProfitLoss = 0; // Total Profit/Loss

                trades.forEach((trade) => {
                    // Safely access and parse trade properties with defaults
                    const pair = trade.pair || "N/A";
                    const orderType = trade.order_type || "N/A";
                    const magicNumber = trade.magic_number || 0;
                    const volume = parseFloat(trade.volume || 0);
                    const profit = parseFloat(trade.profit || 0);
                    const openPrice = parseFloat(trade.open_price || 0);

                    // Update overall totals
                    totalLot += volume;
                    totalTradesCount += 1;
                    totalProfitLoss += profit;

                    // Group by Pair, Type, and Magic Number
                    const key = `${pair}|${orderType}|${magicNumber}`;
                    if (!groupedTrades[key]) {
                        groupedTrades[key] = {
                            pair,
                            type: orderType,
                            magicNumber,
                            totalVolume: 0,
                            totalProfit: 0,
                            totalWeightedPrice: 0, // For Weighted Open Price
                            totalTrades: 0,
                        };
                    }

                    // Update grouped data
                    groupedTrades[key].totalVolume += volume;
                    groupedTrades[key].totalProfit += profit;
                    groupedTrades[key].totalWeightedPrice += volume * openPrice;
                    groupedTrades[key].totalTrades += 1;
                });

                // Populate table with grouped data
                let index = 1;

                Object.values(groupedTrades).forEach((group) => {
                    const weightedOpenPrice =
                        group.totalVolume > 0
                            ? (group.totalWeightedPrice / group.totalVolume).toFixed(5)
                            : "N/A";

                    const row = `
                        <tr>
                            <td>${index++}</td>
                            <td>${group.pair}</td>
                            <td>${group.type}</td>
                            <td class="text-end">${group.totalVolume.toFixed(2)}</td>
                            <td class="text-end">${group.totalProfit.toFixed(2)}</td>
                            <td class="text-end">${weightedOpenPrice}</td>
                            <td class="text-end">-</td> <!-- Stop Loss -->
                            <td class="text-end">-</td> <!-- Take Profit -->
                            <td class="text-end">-</td> <!-- Open Time -->
                            <td>open</td>
                            <td class="text-end">${group.magicNumber}</td>
                            <td class="text-end">${weightedOpenPrice}</td>
                            <td class="text-end">-</td> <!-- Currency Price -->
                            <td class="text-end">${group.totalVolume.toFixed(2)}</td>
                            <td class="text-end">${group.totalTrades}</td>
                            <td class="text-end">${group.totalProfit.toFixed(2)}</td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML("beforeend", row);
                });

                // Append totals as a summary row
                const summaryRow = `
                    <tr class="table-info">
                        <td colspan="12" class="text-end"><strong>Totals:</strong></td>
                        <td class="text-end">-</td>
                        <td class="text-end">${totalLot.toFixed(2)}</td> <!-- Correct Total Lot -->
                        <td class="text-end">${totalTradesCount}</td> <!-- Correct Total Trades -->
                        <td class="text-end">${totalProfitLoss.toFixed(2)}</td> <!-- Total Profit/Loss -->
                    </tr>
                `;
                tableBody.insertAdjacentHTML("beforeend", summaryRow);
            } else {
                console.error("No trades data found or API error.");
            }
        } catch (error) {
            console.error("Error fetching trades:", error);
        }
    }

    // Fetch and update trades every 10 seconds
    document.addEventListener("DOMContentLoaded", () => {
        fetchAndUpdateTrades(); // Initial fetch
        setInterval(fetchAndUpdateTrades, 10000); // Refresh every 10 seconds
    });

    </script>
</body>
</html>
