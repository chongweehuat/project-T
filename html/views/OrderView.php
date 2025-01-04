<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Entry Screen</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Additional styles for proper alignment */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .form-grid div {
            display: flex;
            flex-direction: column;
        }
        textarea {
            resize: none;
            width: 100%;
            height: 4rem;
        }
        button[type="submit"] {
            grid-column: span 2; /* Make the button span both columns */
            padding: 0.75rem;
            font-size: 1rem;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header -->
        <div class="header">
            <h1>Order Entry Screen</h1>
            <div class="account-selection">
                <form method="get">
                    <select name="account_id" id="account_id" onchange="this.form.submit()">
                        <option value="">Select an account</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= htmlspecialchars($account['id'] ?? '', ENT_QUOTES) ?>" 
                                    <?= ($accountId ?? '') == ($account['id'] ?? '') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($account['name'] ?? '', ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid">
            <!-- Risk Status -->
            <div>
                <div class="risk-status">
                    <h3>Risk Status</h3>
                    <?php if ($riskStatus): ?>
                        <p>Daily Drawdown: $<?= number_format($riskStatus['daily_drawdown'] ?? 0, 2) ?> / $<?= number_format($riskStatus['max_daily_drawdown'] ?? 0, 2) ?></p>
                        <p>Total Drawdown: $<?= number_format($riskStatus['total_drawdown'] ?? 0, 2) ?> / $<?= number_format($riskStatus['max_total_drawdown'] ?? 0, 2) ?></p>
                        <p>Warnings: <?= $riskStatus['warnings'] ? 'Yes' : 'No' ?></p>
                        <p>Stopped: <?= $riskStatus['stopped'] ? 'Yes' : 'No' ?></p>
                    <?php else: ?>
                        <p>Please select an account to view risk status.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Form -->
            <div class="new-order">
                <h3>Place a New Order</h3>
                <form method="post" action="/?action=placeOrder">
                    <input type="hidden" name="account_id" value="<?= htmlspecialchars($accountId ?? '', ENT_QUOTES) ?>">
                    <div class="form-grid">
                        <div>
                            <label>Pair:</label>
                            <select name="pair" onchange="updateDefaults()">
                                <option value="">Select a pair</option>
                                <?php foreach ($currencyPairs as $pair): ?>
                                    <option value="<?= htmlspecialchars($pair['pair'] ?? '', ENT_QUOTES) ?>"><?= htmlspecialchars($pair['pair'] ?? '', ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Lots:</label>
                            <input type="number" name="lots" step="0.01">
                        </div>
                        <div>
                            <label>Stop Loss:</label>
                            <input type="number" name="stop_loss" step="0.00001">
                        </div>
                        <div>
                            <label>Take Profit:</label>
                            <input type="number" name="take_profit" step="0.00001">
                        </div>
                        <div>
                            <label>Pending Order Price:</label>
                            <input type="number" name="pending_order_price" step="0.01">
                        </div>
                        <div style="grid-column: span 2;">
                            <label>Remark:</label>
                            <textarea name="remark"></textarea>
                        </div>
                    </div>
                    <button type="submit">Submit Order</button>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        <?php
        if (!isset($pairPrices) || !is_array($pairPrices)) {
            $pairPrices = [];
        }
        if (!isset($priceOffsets) || !is_array($priceOffsets)) {
            $priceOffsets = [];
        }
        ?>
        // Real-time bid/ask prices and price offsets from server-side
        const pairPrices = <?= json_encode($pairPrices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const priceOffsets = <?= json_encode($priceOffsets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Function to update the form fields based on the selected pair
        function updateDefaults() {
            const pair = document.querySelector('select[name="pair"]').value;
            const lotsInput = document.querySelector('input[name="lots"]');
            const stopLossInput = document.querySelector('input[name="stop_loss"]');
            const takeProfitInput = document.querySelector('input[name="take_profit"]');

            if (pair && pairPrices[pair] && priceOffsets[pair]) {
                const bidPrice = parseFloat(pairPrices[pair].bid_price);
                const askPrice = parseFloat(pairPrices[pair].ask_price);
                const sl = parseFloat(priceOffsets[pair].sl);
                const tp = parseFloat(priceOffsets[pair].tp);

                lotsInput.value = 1.0; // Default lot size
                stopLossInput.value = (bidPrice - sl).toFixed(5); // SL below bid
                takeProfitInput.value = (askPrice + tp).toFixed(5); // TP above ask
            } else {
                lotsInput.value = '';
                stopLossInput.value = '';
                takeProfitInput.value = '';
            }
        }
    </script>
</body>
</html>
