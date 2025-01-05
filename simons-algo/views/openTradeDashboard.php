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
            max-width: 95%;
            margin: 20px auto;
        }

        .text-end {
            text-align: right;
        }

        .highlight-positive {
            color: green;
            font-weight: bold;
        }

        .highlight-negative {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mt-4">Open Trades Dashboard <?php echo htmlspecialchars($_GET['login'] ?? ''); ?></h1>
        <div class="table-container">
            <table class="table table-striped table-bordered" id="tradesTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Pair</th>
                        <th>Type</th>
                        <th class="text-end">Total Volume</th>
                        <th class="text-end">Weighted Open Price</th>
                        <th class="text-end">Current Price</th>
                        <th class="text-end">Profit</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows will be dynamically populated -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // API endpoint to fetch grouped trades
        const login = "<?php echo htmlspecialchars($_GET['login'] ?? ''); ?>"; // Account login ID
        const apiEndpoint = `https://sapi.my369.click/getTrades.php?account_id=${login}`;
        

        // Function to fetch and render trades data
        async function fetchAndRenderTrades() {
            try {
                const response = await axios.get(apiEndpoint);

                if (response.data.status === "success" && response.data.data) {
                    const trades = response.data.data;
                    const tableBody = document.querySelector("#tradesTable tbody");

                    // Clear existing rows
                    tableBody.innerHTML = "";

                    // Populate table with trades data
                    trades.forEach((trade, index) => {
                        const profitClass = parseFloat(trade.profit) >= 0 
                            ? "highlight-positive" 
                            : "highlight-negative";

                        const row = `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${trade.pair}</td>
                                <td>${trade.order_type}</td>
                                <td class="text-end">${parseFloat(trade.total_volume).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(trade.weighted_open_price).toFixed(5)}</td>
                                <td class="text-end">${parseFloat(trade.current_price).toFixed(5)}</td>
                                <td class="text-end ${profitClass}">${parseFloat(trade.profit).toFixed(2)}</td>
                                <td>${trade.last_update}</td>
                            </tr>
                        `;

                        tableBody.insertAdjacentHTML("beforeend", row);
                    });
                } else {
                    console.error("Failed to fetch trades:", response.data.message);
                }
            } catch (error) {
                console.error("Error fetching trades:", error);
            }
        }

        // Initialize data fetch and auto-refresh every 10 seconds
        document.addEventListener("DOMContentLoaded", () => {
            fetchAndRenderTrades(); // Initial fetch
            setInterval(fetchAndRenderTrades, 10000); // Refresh every 10 seconds
        });
    </script>
</body>
</html>
