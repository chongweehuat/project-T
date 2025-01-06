<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .table-container {
            max-width: 90%;
            margin: 20px auto;
        }
        .risk-low {
            color: green;
        }
        .risk-medium {
            color: orange;
        }
        .risk-high {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mt-4">Account Dashboard</h1>
        <div class="table-container">
            <table class="table table-striped table-bordered" id="accountTable">
                <thead class="table-dark">
                    <tr>
                        <th>Account</th>
                        <th>Name</th>
                        <th>Prop Firm</th>
                        <th>Balance</th>
                        <th>Equity</th>
                        <th>Free Margin</th>
                        <th>Risk Status</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rows will be dynamically injected here -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        async function fetchAccountData() {
            try {
                const response = await axios.get('https://sapi.my369.click/getAccounts.php');

                if (response.data.status === 'success') {
                    const accounts = response.data.data;
                    const tableBody = document.querySelector('#accountTable tbody');

                    // Clear existing table rows
                    tableBody.innerHTML = '';

                    // Populate table with new data
                    accounts.forEach(account => {
                        // Convert string values to numbers
                        const balance = parseFloat(account.balance);
                        const equity = parseFloat(account.equity);
                        const freeMargin = parseFloat(account.free_margin);

                        // Risk Status Logic
                        let riskStatus = '';
                        let riskClass = '';

                        // Risk Calculations Based on Trading Plan
                        const dailyMaxDrawdown = balance * 0.05;
                        const totalMaxDrawdown = balance * 0.10;
                        const drawdown = balance - equity; // Current drawdown

                        if (drawdown <= dailyMaxDrawdown) {
                            riskStatus = 'Low';
                            riskClass = 'risk-low';
                        } else if (drawdown <= totalMaxDrawdown) {
                            riskStatus = 'Medium';
                            riskClass = 'risk-medium';
                        } else {
                            riskStatus = 'High';
                            riskClass = 'risk-high';
                        }

                        // Generate row with clickable link for account number
                        const row = `
                            <tr>
                                <td>
                                    <a href="?view=openTradeDashboard&account_id=${account.login}" target="_blank">
                                        ${account.login}
                                    </a>
                                </td>
                                <td>${account.name}</td>
                                <td>${account.broker_name || 'N/A'}</td> <!-- Prop Firm Name -->
                                <td>${balance.toFixed(2)}</td>
                                <td>${equity.toFixed(2)}</td>
                                <td>${freeMargin.toFixed(2)}</td>
                                <td class="${riskClass}">${riskStatus}</td>
                                <td>${account.last_update}</td>
                            </tr>
                        `;
                        tableBody.insertAdjacentHTML('beforeend', row);
                    });
                } else {
                    console.error('Error fetching account data:', response.data.message);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Automatically refresh data every 10 seconds
        document.addEventListener('DOMContentLoaded', () => {
            fetchAccountData(); // Load data on page load
            setInterval(fetchAccountData, 10000); // Fetch updates every 10 seconds
        });
    </script>
</body>
</html>
