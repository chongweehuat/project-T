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
                        <!-- New columns -->
                        <th class="text-end">Current Price</th>
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

                    // Initialize totals
                    let totalLot = 0;
                    let totalProfitLoss = 0;
                    let totalTrades = trades.length;

                    // Populate table with new data
                    trades.forEach((trade, index) => {
                        totalLot += parseFloat(trade.volume);
                        totalProfitLoss += parseFloat(trade.profit);

                        const row = `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${trade.pair}</td>
                                <td>${trade.order_type}</td>
                                <td class="text-end">${parseFloat(trade.volume).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(trade.profit).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(trade.open_price).toFixed(5)}</td>
                                <td class="text-end">${parseFloat(trade.stop_loss).toFixed(5)}</td>
                                <td class="text-end">${parseFloat(trade.take_profit).toFixed(5)}</td>
                                <td>${trade.open_time}</td>
                                <td>${trade.status}</td>
                                <td class="text-end">${trade.magic_number}</td>
                                <td class="text-end">${parseFloat(trade.current_price).toFixed(5) || "N/A"}</td>
                                <td class="text-end">${totalLot.toFixed(2)}</td>
                                <td class="text-end">${totalTrades}</td>
                                <td class="text-end">${totalProfitLoss.toFixed(2)}</td>
                            </tr>
                        `;
                        tableBody.insertAdjacentHTML("beforeend", row);
                    });

                    // Append totals as a summary row
                    const summaryRow = `
                        <tr class="table-info">
                            <td colspan="11" class="text-end"><strong>Totals:</strong></td>
                            <td class="text-end">${totalLot.toFixed(2)}</td>
                            <td class="text-end">${totalTrades}</td>
                            <td class="text-end">${totalProfitLoss.toFixed(2)}</td>
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
