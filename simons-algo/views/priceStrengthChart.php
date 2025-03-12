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

    // 将波动数据按货币分组
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

    // 检查数据长度并对齐时间戳
    // 获取价格数据的时间戳
    $priceTimes = array_column($priceData, 'dataTime');
    // 获取第一个货币的波动数据时间戳（假设两个货币的波动数据时间戳一致）
    $volatilityTimes = array_column($currencyData[$currency1], 'dataTime');

    // 找到共同的时间戳
    $commonTimes = array_intersect($priceTimes, $volatilityTimes);

    if (empty($commonTimes)) {
        logMessage("价格数据和波动数据时间戳无交集。");
        throw new Exception("价格数据和波动数据时间戳无交集。");
    }

    // 根据共同时间戳过滤价格数据
    $filteredPriceData = array_filter($priceData, function($entry) use ($commonTimes) {
        return in_array($entry['dataTime'], $commonTimes);
    });
    $filteredPriceData = array_values($filteredPriceData); // 重置索引

    // 根据共同时间戳过滤波动数据
    foreach ($currencyData as &$data) {
        $data = array_filter($data, function($entry) use ($commonTimes) {
            return in_array($entry['dataTime'], $commonTimes);
        });
        $data = array_values($data); // 重置索引
    }
    unset($data); // 解除引用

    // 反转数据（从最旧到最新）
    $priceData = array_reverse($filteredPriceData);
    foreach ($currencyData as &$data) {
        $data = array_reverse($data);
    }
    unset($data); // 解除引用

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
            </select>
            <button onclick="updateCharts()">更新图表</button>
            
            <!-- 添加波动时段开关 -->
            <div style="margin-top: 10px;">
                <label>显示波动时段：</label>
                <input type="checkbox" id="show1h" checked> 1小时
                <input type="checkbox" id="show4h" checked> 4小时
                <input type="checkbox" id="show24h" checked> 24小时
            </div>
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
        let priceData = <?php echo $priceDataJson; ?>;
        let currencyData = <?php echo $currencyDataJson; ?>;
        let selectedPair = <?php echo $selectedPairJson; ?>;
        let combinedChart;

        document.getElementById('pair-select').value = selectedPair;
        document.getElementById('combinedChartTitle').innerText = `${selectedPair} 价格及波动`;

        const labels = priceData.map(entry => entry.dataTime);

        // 从 localStorage 恢复开关状态
        function loadCheckboxStates() {
            const show1h = localStorage.getItem('show1h') === 'false' ? false : true;
            const show4h = localStorage.getItem('show4h') === 'false' ? false : true;
            const show24h = localStorage.getItem('show24h') === 'false' ? false : true;

            document.getElementById('show1h').checked = show1h;
            document.getElementById('show4h').checked = show4h;
            document.getElementById('show24h').checked = show24h;
        }

        // 保存开关状态到 localStorage
        function saveCheckboxStates() {
            localStorage.setItem('show1h', document.getElementById('show1h').checked);
            localStorage.setItem('show4h', document.getElementById('show4h').checked);
            localStorage.setItem('show24h', document.getElementById('show24h').checked);
        }

        // 创建数据集生成函数
        function getDatasets() {
            const show1h = document.getElementById('show1h').checked;
            const show4h = document.getElementById('show4h').checked;
            const show24h = document.getElementById('show24h').checked;

            let datasets = [
                {
                    label: `${selectedPair} 价格`,
                    data: priceData.map(entry => entry.price),
                    borderColor: '#36A2EB',
                    fill: false,
                    yAxisID: 'y-price',
                }
            ];

            const currency1 = selectedPair.substring(0, 3);
            const currency2 = selectedPair.substring(3, 6);

            if (show1h) {
                datasets.push({
                    label: `${currency1} 1小时波动`,
                    data: currencyData[currency1].map(entry => entry.value1),
                    borderColor: '#FF6384',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }
            if (show4h) {
                datasets.push({
                    label: `${currency1} 4小时波动`,
                    data: currencyData[currency1].map(entry => entry.value4),
                    borderColor: '#FF9F40',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }
            if (show24h) {
                datasets.push({
                    label: `${currency1} 24小时波动`,
                    data: currencyData[currency1].map(entry => entry.value24),
                    borderColor: '#FFCD56',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }

            if (show1h) {
                datasets.push({
                    label: `${currency2} 1小时波动`,
                    data: currencyData[currency2].map(entry => entry.value1),
                    borderColor: '#4BC0C0',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }
            if (show4h) {
                datasets.push({
                    label: `${currency2} 4小时波动`,
                    data: currencyData[currency2].map(entry => entry.value4),
                    borderColor: '#9966FF',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }
            if (show24h) {
                datasets.push({
                    label: `${currency2} 24小时波动`,
                    data: currencyData[currency2].map(entry => entry.value24),
                    borderColor: '#C9CBCF',
                    fill: false,
                    yAxisID: 'y-volatility',
                });
            }

            return datasets;
        }

        // 初始化图表
        function initChart() {
            const ctxCombined = document.getElementById('combinedChart').getContext('2d');
            combinedChart = new Chart(ctxCombined, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: getDatasets()
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
                                drawOnChartArea: false,
                            },
                        },
                    },
                },
            });
        }

        // 更新图表显示
        function refreshChart() {
            if (combinedChart) {
                combinedChart.data.datasets = getDatasets();
                combinedChart.update();
                saveCheckboxStates(); // 每次更改时保存状态
            }
        }

        // 绑定开关事件
        document.getElementById('show1h').addEventListener('change', refreshChart);
        document.getElementById('show4h').addEventListener('change', refreshChart);
        document.getElementById('show24h').addEventListener('change', refreshChart);

        // 更新图表（货币对选择）
        function updateCharts() {
            const selectedPair = document.getElementById('pair-select').value;
            saveCheckboxStates(); // 保存当前选择状态
            window.location.href = `?view=priceStrengthChart&pair=${selectedPair}`;
        }

        // 初始化
        loadCheckboxStates(); // 首先加载保存的状态
        initChart();
    </script>
</body>
</html>