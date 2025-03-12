<?php
require_once '/var/www/db/database.php'; // 引入数据库连接类
require_once '/var/www/utils/Logger.php'; // 引入日志工具

try {
    // 连接数据库
    $db = Database::connect('volatility');
    if (!$db) {
        logMessage("数据库连接失败");
        throw new Exception("数据库连接失败");
    }

    // 设置默认货币配对
    $selectedPair = isset($_GET['pair']) ? $_GET['pair'] : 'GBPJPY';
    $currency1 = substr($selectedPair, 0, 3); // 例如 GBP
    $currency2 = substr($selectedPair, 3, 3); // 例如 JPY

    // 查询价格数据
    $sqlPrice = "
        SELECT price, dataTime 
        FROM price_data 
        WHERE symbol = :pair 
        ORDER BY dataTime DESC 
        LIMIT 300
    ";
    $stmtPrice = $db->prepare($sqlPrice);
    $stmtPrice->bindParam(':pair', $selectedPair);
    $stmtPrice->execute();
    $priceData = $stmtPrice->fetchAll(PDO::FETCH_ASSOC);

    // 查询波动数据
    $sqlVolatility = "
        SELECT currency, value1, value4, value24, dataTime 
        FROM currency_volatility 
        WHERE currency IN (:currency1, :currency2) 
        ORDER BY dataTime DESC 
        LIMIT 300
    ";
    $stmtVolatility = $db->prepare($sqlVolatility);
    $stmtVolatility->bindParam(':currency1', $currency1);
    $stmtVolatility->bindParam(':currency2', $currency2);
    $stmtVolatility->execute();
    $volatilityData = $stmtVolatility->fetchAll(PDO::FETCH_ASSOC);

    // 检查数据是否为空
    if (!$priceData || !$volatilityData) {
        logMessage("未找到数据: 价格数据或波动数据为空");
        throw new Exception("未找到数据。");
    }

    // 将波动数据按货币分组
    $currencyData = [];
    foreach ($volatilityData as $data) {
        $currency = $data['currency'];
        if (!isset($currencyData[$currency])) {
            $currencyData[$currency] = [];
        }
        $currencyData[$currency][] = $data;
    }

    // 记录日志以便调试
    logMessage("价格数据条数: " . count($priceData));
    logMessage("波动数据条数: " . count($volatilityData));
    logMessage("价格数据时间戳范围: " . $priceData[0]['dataTime'] . " 至 " . end($priceData)['dataTime']);
    logMessage("波动数据时间戳范围: " . $volatilityData[0]['dataTime'] . " 至 " . end($volatilityData)['dataTime']);

} catch (PDOException $e) {
    logMessage("数据库错误: " . $e->getMessage());
    die("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("系统错误: " . $e->getMessage());
    die("系统错误: " . $e->getMessage());
} finally {
    $stmtPrice = null;
    $stmtVolatility = null;
    $db = null;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库数据调试 - <?php echo $selectedPair; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #fff;
        }
        tr:nth-child(odd) {
            background-color: #f9f9f9;
        }
        .json-output {
            margin-top: 20px;
            padding: 10px;
            background-color: #e9ecef;
            border: 1px solid #ddd;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: 'Courier New', Courier, monospace;
        }
        .pair-selector {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>数据库数据调试 - <?php echo $selectedPair; ?> 价格和波动数据</h1>

        <!-- 货币配对选择器 -->
        <div class="pair-selector">
            <label for="pair-select">选择货币配对：</label>
            <select id="pair-select" onchange="window.location.href='?pair='+this.value">
                <option value="GBPJPY" <?php echo $selectedPair === 'GBPJPY' ? 'selected' : ''; ?>>GBPJPY</option>
                <option value="EURUSD" <?php echo $selectedPair === 'EURUSD' ? 'selected' : ''; ?>>EURUSD</option>
                <option value="USDJPY" <?php echo $selectedPair === 'USDJPY' ? 'selected' : ''; ?>>USDJPY</option>
            </select>
        </div>

        <!-- 价格数据表格 -->
        <h2>价格数据 (price_data)</h2>
        <table>
            <thead>
                <tr>
                    <th>时间 (dataTime)</th>
                    <th>价格 (price)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($priceData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['dataTime']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($row['price'], 6)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 波动数据表格 -->
        <h2>波动数据 (currency_volatility)</h2>
        <table>
            <thead>
                <tr>
                    <th>货币 (currency)</th>
                    <th>时间 (dataTime)</th>
                    <th>1小时波动 (value1)</th>
                    <th>4小时波动 (value4)</th>
                    <th>24小时波动 (value24)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($volatilityData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['dataTime']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($row['value1'], 4)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($row['value4'], 4)); ?></td>
                        <td><?php echo htmlspecialchars(number_format($row['value24'], 4)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- JSON 输出 -->
        <h2>JSON 数据</h2>
        <div class="json-output">
            <h3>价格数据 (priceData)</h3>
            <pre><?php echo htmlspecialchars(json_encode($priceData, JSON_PRETTY_PRINT)); ?></pre>
            <h3>波动数据 (currencyData)</h3>
            <pre><?php echo htmlspecialchars(json_encode($currencyData, JSON_PRETTY_PRINT)); ?></pre>
        </div>
    </div>
</body>
</html>