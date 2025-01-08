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
                        <th class="text-end">Vol</th>
                        <th class="text-end">WOP</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">XP</th>
                        <th class="text-end">Gap</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">SL</th> <!-- New Column -->
                        <th class="text-end">TP</th> <!-- New Column -->
                        <th class="text-end">RRR</th> <!-- New Column -->
                        <th class="text-end">Drawdown</th> <!-- New Column -->
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

        function formatByPointValue(value, pointValue) {
            const decimals = pointValue ? Math.max(0, Math.floor(-Math.log10(parseFloat(pointValue)))) : 5;
            return parseFloat(value).toFixed(decimals);
        }

        async function fetchAccountDetails() {
            const accountDetailsDiv = document.getElementById("accountDetails");
            try {
                const response = await axios.get(accountEndpoint);
                if (response.data.status === "success" && response.data.data.length > 0) {
                    const account = response.data.data[0];
                    accountDetailsDiv.innerHTML = `
                        <div>${account.name}</div>
                        <div>${account.broker_name}</div>
                        <div>${account_id}</div>
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
                    const account = await axios.get(accountEndpoint); // Fetch account details to get balance
                    const accountBalance = parseFloat(account.data.data[0].balance);

                    const tableBody = document.querySelector("#tradesTable tbody");
                    tableBody.innerHTML = "";

                    trades.forEach((trade, index) => {
                        const profitClass = parseFloat(trade.profit) >= 0 
                            ? "highlight-positive" 
                            : "highlight-negative";

                        const gap = trade.extreme_price !== null && trade.current_price !== null
                            ? Math.round((parseFloat(trade.current_price) - parseFloat(trade.extreme_price)) / parseFloat(trade.point_value))
                            : 'N/A';

                        const rrr = trade.take_profit && trade.stop_loss
                            ? Math.abs(((parseFloat(trade.take_profit) - parseFloat(trade.weighted_open_price)) / (parseFloat(trade.weighted_open_price) - parseFloat(trade.stop_loss)))).toFixed(1)
                            : 'N/A';

                        const drawdown = trade.stop_loss
                            ? (
                                Math.abs(
                                    (
                                        ((parseFloat(trade.weighted_open_price) - parseFloat(trade.stop_loss)) / parseFloat(trade.point_value)) * 
                                        parseFloat(trade.total_volume)
                                    ) / accountBalance * 1000
                                )
                            ).toFixed(2) + "%"
                            : 'N/A';

                        const row = `
                            <tr id="trade-${trade.id}" class="highlight-update">
                                <td>${index + 1}</td>
                                <td>${trade.magic_number || "-"}</td>
                                <td>
                                    <a href="https://sa.my369.click?view=openTradesListing&group_id=${trade.group_id}" target="_blank">
                                        ${trade.pair}
                                    </a>
                                </td>
                                <td>${trade.order_type}</td>
                                <td class="text-end">${parseFloat(trade.total_volume).toFixed(2)}</td> <!-- Vol: 2 decimal -->
                                <td class="text-end">${formatByPointValue(trade.weighted_open_price, trade.point_value)}</td>
                                <td class="text-end">${formatByPointValue(trade.current_price, trade.point_value)}</td>
                                <td class="text-end">${trade.extreme_price !== null ? formatByPointValue(trade.extreme_price, trade.point_value) : '-'}</td>
                                <td class="text-end">${gap}</td> <!-- Gap: integer -->
                                <td class="text-end ${profitClass}">${parseFloat(trade.profit).toFixed(2)}</td> <!-- Profit: 2 decimal -->
                                <td class="text-end">${trade.stop_loss ? formatByPointValue(trade.stop_loss, trade.point_value) : '-'}</td>
                                <td class="text-end">${trade.take_profit ? formatByPointValue(trade.take_profit, trade.point_value) : '-'}</td>
                                <td class="text-end">${rrr}</td> <!-- RRR: 1 decimal -->
                                <td class="text-end">${drawdown}</td>
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
