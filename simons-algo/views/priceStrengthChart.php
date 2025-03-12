<?php
require_once '/var/www/db/database.php'; // 引入数据库连接类
require_once '/var/www/utils/Logger.php'; // 引入日志工具

try {
    // 连接数据库
    $db = Database::connect('volatility'); // 使用原有的数据库连接模式

    // 检查数据库连接是否成功
    if (!$db) {
        logMessage("数据库连接失败");
        throw new Exception("数据库连接失败");
    }

    // 获取用户选择的货币配对
    $selectedPair = isset($_GET['pair']) ? $_GET['pair'] : 'GBPJPY';

    // 获取最新的 300 条选定货币配对的价格数据
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

    // 获取选定货币配对的相关货币
    $currency1 = substr($selectedPair, 0, 3); // 前三个字符为第一个货币
    $currency2 = substr($selectedPair, 3, 3); // 后三个字符为第二个货币

    // 获取最新的 300 条相关货币的波动数据
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

    if (!$priceData || !$volatilityData) {
        logMessage("没有找到数据。");
        throw new Exception("没有找到数据。");
    }

    // 将数据按货币分组
    $currencyData = [];
    foreach ($volatilityData as $data) {
        $currency = $data['currency'];
        if (!isset($currencyData[$currency])) {
            $currencyData[$currency] = [];
        }
        $currencyData[$currency][] = [
            'value1' => $data['value1'],
            'value4' => $data['value4'],
            'value24' => $data['value24'],
            'dataTime' => $data['dataTime'],
        ];
    }

    // 释放资源
    $stmtPrice = null;
    $stmtVolatility = null;
    $db = null;
} catch (PDOException $e) {
    logMessage("数据库错误: " . $e->getMessage());
    die("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("系统错误: " . $e->getMessage());
    die("系统错误: " . $e->getMessage());
}

// 将数据转换为 JSON 格式
$priceDataJson = json_encode($priceData);
$currencyDataJson = json_encode($currencyData);
$selectedPairJson = json_encode($selectedPair);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- 每分钟自动刷新 -->
    <title>货币配对价格及波动曲线图表</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h1 {
            text-align: center;
        }
        .chart-container {
            margin-top: 20px;
        }
        canvas {
            margin-bottom: 40px;
        }
        .pair-selector {
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>货币配对价格及波动曲线图表</h1>

        <!-- 货币配对选择器 -->
        <div class="pair-selector">
            <label for="pair-select">选择货币配对：</label>
            <select id="pair-select">
                <option value="GBPJPY">GBPJPY</option>
                <option value="EURUSD">EURUSD</option>
                <option value="USDJPY">USDJPY</option>
                <!-- 添加其他货币配对选项 -->
            </select>
            <button onclick="updateCharts()">更新图表</button>
        </div>

        <!-- 合并的图表 -->
        <div class="chart-container">
            <h2 id="combinedChartTitle"></h2>
            <canvas id="combinedChart"></canvas>
        </div>
    </div>

    <!-- 引入 Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 获取 PHP 传递的 JSON 数据
        let priceData = <?php echo $priceDataJson; ?>;
        let currencyData = <?php echo $currencyDataJson; ?>;
        let selectedPair = <?php echo $selectedPairJson; ?>;

        // 设置初始选择的货币配对
        document.getElementById('pair-select').value = selectedPair;

        // 更新图表标题
        document.getElementById('combinedChartTitle').innerText = `${selectedPair} 价格及波动`;

        // 提取时间戳作为 X 轴标签
        const labels = priceData.map(entry => entry.dataTime);

        // 创建合并的图表
        const ctxCombined = document.getElementById('combinedChart').getContext('2d');
        const combinedChart = new Chart(ctxCombined, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    // 价格数据
                    {
                        label: `${selectedPair} 价格`,
                        data: priceData.map(entry => entry.price),
                        borderColor: '#36A2EB', // 蓝色
                        fill: false,
                        yAxisID: 'y-price', // 使用价格轴
                    },
                    // 货币1的波动数据
                    {
                        label: `${selectedPair.substring(0, 3)} 1小时波动`,
                        data: currencyData[selectedPair.substring(0, 3)].map(entry => entry.value1),
                        borderColor: '#FF6384', // 红色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                    {
                        label: `${selectedPair.substring(0, 3)} 4小时波动`,
                        data: currencyData[selectedPair.substring(0, 3)].map(entry => entry.value4),
                        borderColor: '#FF9F40', // 橙色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                    {
                        label: `${selectedPair.substring(0, 3)} 24小时波动`,
                        data: currencyData[selectedPair.substring(0, 3)].map(entry => entry.value24),
                        borderColor: '#FFCD56', // 黄色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                    // 货币2的波动数据
                    {
                        label: `${selectedPair.substring(3, 6)} 1小时波动`,
                        data: currencyData[selectedPair.substring(3, 6)].map(entry => entry.value1),
                        borderColor: '#4BC0C0', // 绿色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                    {
                        label: `${selectedPair.substring(3, 6)} 4小时波动`,
                        data: currencyData[selectedPair.substring(3, 6)].map(entry => entry.value4),
                        borderColor: '#9966FF', // 紫色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                    {
                        label: `${selectedPair.substring(3, 6)} 24小时波动`,
                        data: currencyData[selectedPair.substring(3, 6)].map(entry => entry.value24),
                        borderColor: '#C9CBCF', // 灰色
                        fill: false,
                        yAxisID: 'y-volatility', // 使用波动轴
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: `${selectedPair} 价格及波动`,
                    },
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: '时间',
                        },
                    },
                    yPrice: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: '价格',
                        },
                        id: 'y-price',
                    },
                    yVolatility: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: '波动值',
                        },
                        id: 'y-volatility',
                        grid: {
                            drawOnChartArea: false, // 避免与左侧轴重叠
                        },
                    },
                },
            },
        });

        // 更新图表
        function updateCharts() {
            const selectedPair = document.getElementById('pair-select').value;
            window.location.href = `?pair=${selectedPair}`;
        }
    </script>
</body>
</html>