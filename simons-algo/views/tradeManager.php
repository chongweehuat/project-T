<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .container { max-width: 95%; margin: 20px auto; }
        .account-details { margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 5px; background-color: #f8f9fa; display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
        .text-end { text-align: right; }
        .highlight-positive { color: green !important; font-weight: bold; }
        .highlight-negative { color: red !important; font-weight: bold; }
        .editable { cursor: pointer; text-decoration: underline; color: blue; }
        .table-container { margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div id="accountDetails" class="account-details">
            <p>Loading account details...</p>
        </div>

        <div id="combinedTableContainer" class="table-container">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>MG</th>
                        <th>Pair</th>
                        <th>Type</th>
                        <th>Remark</th>
                        <th>FT</th>
                        <th>AT</th>
                        <th>CP</th>
                        <th>SL</th>
                        <th>CL</th>
                        <th class="text-end">Lot</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">DD %</th>
                        <th class="text-end">Risk %</th>
                        <th class="text-end">Pips</th>
                        <th class="text-end">Stop Loss</th>
                        <th class="text-end">Take Profit</th>
                        <th class="text-end">Trigger Price</th>
                        <th class="text-end">Trailing Stop (Pips)</th>
                        <th class="text-end">Extreme Price</th>
                        <th class="text-end">Current Price</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody id="combinedTableBody">
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const params = new URLSearchParams(window.location.search);
        const account_id = params.get("account_id");

        const accountEndpoint = `https://sapi.my369.click/getAccountByID.php?account_id=${account_id}`;
        const combinedEndpoint = `https://sapi.my369.click/getCombinedTrades.php?account_id=${account_id}`;
        const updateTradeParamEndpoint = `https://sapi.my369.click/updateTradeParam.php`;

        async function fetchAccountDetails() {
            const accountDetailsDiv = document.getElementById("accountDetails");
            try {
                const response = await axios.get(accountEndpoint);
                if (response.data.status === "success" && response.data.data.length > 0) {
                    const account = response.data.data[0];
                    const balance = parseFloat(account.balance) || 0;
                    const equity = parseFloat(account.equity) || 0;
                    const freeMargin = parseFloat(account.free_margin) || 0;
                    const margin = parseFloat(account.margin) || 0;
                    const totalVolume = parseFloat(account.total_volume) || 0;
                    const openCount = parseInt(account.open_count, 10) || 0;
                    const acfloat = equity - balance;

                    accountDetailsDiv.innerHTML = `
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Broker</th>
                                    <th>Account ID</th>
                                    <th>BAL</th>
                                    <th>Peak Equity</th>
                                    <th>Float</th>
                                    <th>Free Margin</th>
                                    <th>Margin</th>
                                    <th>Lots</th>
                                    <th>Open</th>
                                    <th>Last Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>${account.name}</td>
                                    <td>${account.broker_name}</td>
                                    <td>${account_id}</td>
                                    <td>${balance.toFixed(2)}</td>
                                    <td>${equity.toFixed(2)}</td>
                                    <td>${acfloat.toFixed(2)} (${((100 * acfloat) / (equity || 1)).toFixed(2)}%)</td>
                                    <td>${freeMargin.toFixed(2)}</td>
                                    <td>${margin.toFixed(2)}</td>
                                    <td>${totalVolume.toFixed(2)}</td>
                                    <td>${openCount}</td>
                                    <td>${account.last_update}</td>
                                </tr>
                            </tbody>
                        </table>
                    `;
                } else {
                    accountDetailsDiv.innerHTML = `<p style="color: red;">Failed to load account details</p>`;
                }
            } catch (error) {
                accountDetailsDiv.innerHTML = `<p style="color: red;">Error loading account details: ${error.message}</p>`;
            }
        }

        async function fetchCombinedData() {
            try {
                const response = await axios.get(combinedEndpoint);
                const data = response.data.data || [];
                const tableBody = document.getElementById("combinedTableBody");

                tableBody.innerHTML = "";

                data.forEach((entry, index) => {
                    const lotSize = parseFloat(entry.total_volume || 0);
                    const extremeOpenPrice = parseFloat(entry.extreme_open_price || 0);
                    const currentPrice = parseFloat(entry.current_price || 0);
                    const extremePrice = parseFloat(entry.extreme_price || 0);
                    const stopLoss = parseFloat(entry.stop_loss || 0);
                    const weightedOpenPrice = parseFloat(entry.weighted_open_price || 0);
                    const pointValue = parseFloat(entry.point_value || 1);
                    const profit = parseFloat(entry.profit || 0);
                    const currentEquity = parseFloat(entry.account_equity || 0); // Use account equity from API

                    let drawdown = "N/A";
                    let risk = "N/A";
                    let pips = "N/A";

                    // Calculate Drawdown
                    if (currentEquity > 0) {
                        const currentLoss = -profit;
                        drawdown = ((currentLoss / currentEquity) * 100).toFixed(2);
                        drawdown = parseFloat(drawdown) > 0 
                            ? `<span class="highlight-negative">${drawdown}</span>` 
                            : `<span class="highlight-positive">${drawdown}</span>`;
                    }

                    // Calculate Risk
                    if (lotSize > 0 && stopLoss > 0 && weightedOpenPrice > 0 && currentEquity > 0) {
                        const pipsToSL = Math.abs(weightedOpenPrice - stopLoss) * 10 / pointValue;
                        const riskValue = (lotSize * pipsToSL) / currentEquity;
                        risk = (riskValue * 100).toFixed(2);
                    }

                    // Calculate Pips
                    if (extremeOpenPrice > 0 && currentPrice > 0) {
                        const pipsValue = entry.order_type === "sell"
                            ? ((currentPrice - extremeOpenPrice) * 10 / pointValue)
                            : ((extremeOpenPrice - currentPrice) * 10 / pointValue);
                        pips = parseFloat(pipsValue).toFixed(0);
                        pips = pipsValue > 0 
                            ? `<span class="highlight-negative">${pips}</span>` 
                            : `<span class="highlight-positive">${pips}</span>`;
                    }

                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${entry.magic_number}</td>
                            <td>${entry.pair}</td>
                            <td>${entry.order_type || "N/A"}</td>
                            <td>${entry.remark || "N/A"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-param="auth_FT">${entry.auth_FT ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-param="auth_AT">${entry.auth_AT ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-param="auth_CP">${entry.auth_CP ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-param="auth_SL">${entry.auth_SL ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-param="auth_CL">${entry.auth_CL ? "Y" : "N"}</td>
                            <td class="text-end">${lotSize.toFixed(2)}</td>
                            <td class="text-end ${profit >= 0 ? "highlight-positive" : "highlight-negative"}">${profit.toFixed(2)}</td>
                            <td class="text-end">${drawdown}</td>
                            <td class="text-end">${risk}</td>
                            <td class="text-end">${pips}</td>
                            <td class="editable text-end" data-id="${entry.config_id}" data-param="stop_loss">${stopLoss.toFixed(5)}</td>
                            <td class="editable text-end" data-id="${entry.config_id}" data-param="take_profit">${parseFloat(entry.take_profit || 0).toFixed(5)}</td>
                            <td class="editable text-end" data-id="${entry.config_id}" data-param="trigger_price">${parseFloat(entry.trigger_price).toFixed(5)}</td>
                            <td class="editable text-end" data-id="${entry.config_id}" data-param="trailing_stop">${entry.trailing_stop}</td>
                            <td class="text-end">${extremePrice}</td>
                            <td class="text-end">${currentPrice}</td>
                            <td>${entry.last_update || "N/A"}</td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML("beforeend", row);
                });

                enableInlineEditing();
            } catch (error) {
                console.error("Error fetching combined data:", error);
            }
        }

        function enableInlineEditing() {
            const editableElements = document.querySelectorAll(".editable");
            editableElements.forEach(element => {
                element.addEventListener("click", async function () {
                    const id = this.dataset.id;
                    const param = this.dataset.param;
                    const currentValue = this.textContent.trim();

                    const isNumericField = param === "stop_loss" || param === "take_profit" || param === "trigger_price" || param === "trailing_stop";
                    const newValue = isNumericField
                        ? prompt(`Enter new value for ${param} (current: ${currentValue})`, currentValue)
                        : currentValue === "Y" ? "N" : "Y";

                    if (isNumericField && (isNaN(newValue) || newValue === null || newValue === "")) {
                        alert("Invalid input. Please enter a valid number.");
                        return;
                    }

                    try {
                        await axios.post(updateTradeParamEndpoint, {
                            config_id: id,
                            param,
                            value: isNumericField ? parseFloat(newValue).toFixed(5) : (newValue === "Y" ? 1 : 0)
                        });

                        this.textContent = isNumericField ? parseFloat(newValue).toFixed(5) : newValue;
                    } catch (error) {
                        console.error("Error updating parameter:", error);
                        alert("Failed to update parameter.");
                    }
                });
            });
        }

        document.addEventListener("DOMContentLoaded", () => {
            fetchAccountDetails();
            fetchCombinedData();
            setInterval(fetchAccountDetails, 30000);
            setInterval(fetchCombinedData, 10000);
        });
    </script>
</body>
</html>
