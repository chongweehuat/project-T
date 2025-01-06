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

        .account-details {
            max-width: 95%;
            margin: 20px auto;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f8f9fa;
        }

        .text-end {
            text-align: right;
        }

        .highlight-positive {
            color: green;
            font-weight: bold;
        }

        .highlight-negative {
            color: red !important;
            font-weight: bold;
        }

        .highlight-update {
            background-color: yellow;
            transition: background-color 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="accountDetails" class="account-details">
            <!-- Account details will be dynamically populated here -->
            <p>Loading account details...</p>
        </div>

        <div id="errorMessage" style="display: none; color: red; text-align: center;"></div>
        <div class="table-container">
            <table class="table table-striped table-bordered" id="tradesTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>MG</th>
                        <th>Pair</th>
                        <th>Type</th>
                        <th>Vol</th>
                        <th>WOP</th>
                        <th>Price</th>
                        <th>Profit</th>
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
        const account_id = "<?php echo htmlspecialchars($_GET['account_id'] ?? ''); ?>";
        const accountEndpoint = `https://sapi.my369.click/getAccountByID.php?account_id=${account_id}`;
        const tradesEndpoint = `https://sapi.my369.click/getTrades.php?account_id=${account_id}`;

        async function fetchAccountDetails() {
            const accountDetailsDiv = document.getElementById("accountDetails");
            try {
                const response = await axios.get(accountEndpoint);
                if (response.data.status === "success" && response.data.data.length > 0) {
                    const account = response.data.data[0];
                    accountDetailsDiv.innerHTML = `
                        <div>${account.name}</div>
                        <div>${account.broker_name}</div>
                        <div> ${account_id}</div>
                        <div>BAL: ${parseFloat(account.balance).toFixed(2)}</div>
                        <div>EQT: ${parseFloat(account.equity).toFixed(2)}</div>
                        <div>FM: ${parseFloat(account.free_margin).toFixed(2)}</div>
                        <div>${account.last_update}</div>
                    `;
                } else {
                    accountDetailsDiv.innerHTML = `<p style="color: red;">Failed to load account details</p>`;
                }
            } catch (error) {
                accountDetailsDiv.innerHTML = `<p style="color: red;">Error loading account details: ${error.message}</p>`;
            }
        }

        async function fetchAndRenderTrades() {
            const errorMessage = document.getElementById("errorMessage");
            errorMessage.style.display = "none";

            try {
                const response = await axios.get(tradesEndpoint);

                if (response.data.status === "success" && response.data.data) {
                    const trades = response.data.data;
                    const tableBody = document.querySelector("#tradesTable tbody");

                    tableBody.innerHTML = "";

                    trades.forEach((trade, index) => {
                        const profitClass = parseFloat(trade.profit) >= 0 
                            ? "highlight-positive" 
                            : "highlight-negative";

                        const row = `
                            <tr id="trade-${trade.id}" class="highlight-update">
                                <td>${index + 1}</td>
                                <td>${trade.magic_number || "-"}</td>
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
                    errorMessage.textContent = "Failed to fetch trades: " + response.data.message;
                    errorMessage.style.display = "block";
                }
            } catch (error) {
                errorMessage.textContent = "Error fetching trades: " + error.message;
                errorMessage.style.display = "block";
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            fetchAccountDetails();
            fetchAndRenderTrades();
            setInterval(fetchAccountDetails, 10000); // Refresh account details every 10 seconds
            setInterval(fetchAndRenderTrades, 10000); // Refresh trades every 10 seconds
        });
    </script>
</body>
</html>
