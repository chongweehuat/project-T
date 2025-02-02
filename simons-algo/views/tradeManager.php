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
        <!-- Account Details -->
        <div id="accountDetails" class="account-details">
            <p>Loading account details...</p>
        </div>

        <!-- Combined Config and Group Data -->
        <div id="combinedTableContainer" class="table-container">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">MG</th>
                        <th rowspan="2">Pair</th>
                        <th rowspan="2">Type</th>
                        <th rowspan="2">Remark</th>
                        <th colspan="5" class="text-center">Auth</th>
                        <th rowspan="2" class="text-end">Lot</th>
                        <th rowspan="2" class="text-end">Profit</th>
                        <th colspan="2" class="text-center">Risk</th>
                        <th rowspan="2" class="text-end">Pips</th>
                        <th rowspan="2" class="text-end">Stop Loss</th>
                        <th rowspan="2" class="text-end">Take Profit</th>
                        <th rowspan="2">Last Updated</th>
                    </tr>
                    <tr>
                        <th>FT</th>
                        <th>AT</th>
                        <th>CP</th>
                        <th>SL</th>
                        <th>CL</th>
                        <th class="text-end">DD %</th>
                        <th class="text-end">Risk %</th>
                    </tr>
                </thead>
                <tbody id="combinedTableBody">
                    <!-- Rows dynamically populated -->
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

        function formatNumber(value, decimals) {
            return parseFloat(value).toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        async function fetchAccountDetails() {
            const accountDetailsDiv = document.getElementById("accountDetails");
            try {
                const response = await axios.get(accountEndpoint);
                if (response.data.status === "success" && response.data.data.length > 0) {
                    const account = response.data.data[0];
                    const acfloat = account.equity-account.balance;
                    accountDetailsDiv.innerHTML = `
                        <div>${account.name}</div>
                        <div>${account.broker_name}</div>
                        <div>${account_id}</div>
                        <div>BAL: ${formatNumber(account.balance, 2)}</div>
                        <div id="peakEquity">Peak Equity: ${formatNumber(account.equity, 2)}</div>
                        <div>Float: ${formatNumber(acfloat, 2)} (${formatNumber(100*acfloat/account.equity, 2)}%)</div>
                        <div>FM: ${formatNumber(account.free_margin, 2)}</div>
                        <div>${account.last_update}</div>
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
                const peakEquityElement = document.getElementById("peakEquity");
                const currentEquity = peakEquityElement
                        ? parseFloat(peakEquityElement.textContent.split(":")[1].replace(/,/g, '')) || 0
                        : 0;

                tableBody.innerHTML = "";

                data.forEach((entry, index) => {
                    const lotSize = parseFloat(entry.total_volume || 0);
                    const extreme_open_price = parseFloat(entry.extreme_open_price || 0);
                    const current_price = parseFloat(entry.current_price || 0);
                    const stopLoss = parseFloat(entry.stop_loss || 0);
                    const weighted_open_price = parseFloat(entry.weighted_open_price || 0);
                    const pointValue = parseFloat(entry.point_value || 1); // Default point value
                    const profit = parseFloat(entry.profit ?? 0);
                    const remark = entry.remark ?? "N/A";

                    let drawdown = "N/A";
                    let risk = "N/A";
                    let pips = "N/A";

                    // Drawdown Calculation
                    if (currentEquity > 0) {
                        const currentLoss = -profit; // Current loss is negative profit
                        drawdown = ((currentLoss / currentEquity) * 100).toFixed(2);
                                                
                        // Mark for risk control if drawdown exceeds 5%
                        if (parseFloat(drawdown) > 5) {
                            drawdown = `<span class="highlight-negative">${drawdown}</span>`;
                        }
                    }

                    
                    // Risk Calculation
                    if (lotSize > 0 && pointValue > 0 && stopLoss > 0 && weighted_open_price > 0) {
                        const pips = 10*Math.abs(weighted_open_price - stopLoss) / pointValue;
                        const riskValue = (lotSize * pips) / currentEquity;
                        risk = (riskValue * 100).toFixed(2);
                          
                        // Mark for risk control if risk exceeds 10%
                        if (parseFloat(risk) > 10) {
                            risk = `<span class="highlight-negative">${risk}</span>`;
                        }
                    }

                    // Calculate Pips for re-entry trade
                    if (extreme_open_price > 0 && current_price > 0) {
                        if (entry.order_type === "sell") {
                            pips = ((current_price - extreme_open_price) * 10 / pointValue).toFixed(0);
                        } else if (entry.order_type === "buy") {
                            pips = ((extreme_open_price - current_price) * 10 / pointValue).toFixed(0);
                        }
                    }

                    const row = `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${entry.magic_number}</td>
                            <td>${entry.pair}</td>
                            <td>${entry.order_type || "N/A"}</td>
                            <td>${remark}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="auth_FT">${entry.auth_FT ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="auth_AT">${entry.auth_AT ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="auth_CP">${entry.auth_CP ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="auth_SL">${entry.auth_SL ? "Y" : "N"}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="auth_CL">${entry.auth_CL ? "Y" : "N"}</td>
                            <td class="text-end">${lotSize.toFixed(2)}</td>
                            <td class="text-end ${profit >= 0 ? "highlight-positive" : "highlight-negative"}">${profit.toFixed(2)}</td>
                            <td class="text-end">${drawdown}</td>
                            <td class="text-end">${risk}</td>
                            <td class="text-end ${pips <0 ? "highlight-positive" : "highlight-negative"}">${pips}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="stop_loss" style="text-align: right;">${stopLoss.toFixed(5)}</td>
                            <td class="editable" data-id="${entry.config_id}" data-groupid="${entry.group_id}" data-param="take_profit" style="text-align: right;">${parseFloat(entry.take_profit || 0).toFixed(5)}</td>
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
                    const groupid = this.dataset.groupid;
                    const param = this.dataset.param;
                    const currentValue = this.textContent.trim();

                    const isNumericField = param === "stop_loss" || param === "take_profit";
                    const newValue = isNumericField
                        ? prompt(`Enter new value for ${param} (current: ${currentValue})`, currentValue)
                        : currentValue === "Y" ? "N" : "Y";

                    if (isNumericField && (isNaN(newValue) || newValue === null || newValue === "")) {
                        alert("Invalid input. Please enter a valid number.");
                        return;
                    }

                    try {
                        await axios.post(updateTradeParamEndpoint, {
                            config_id: id || null,
                            group_id: groupid || null,
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
            setInterval(fetchAccountDetails, 30000); // Refresh account details every 30 seconds
            setInterval(fetchCombinedData, 10000); // Refresh combined data every 10 seconds
        });
    </script>
</body>
</html>
