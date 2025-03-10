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

    // 获取最新的 300 条货币波动数据
    $sql = "
        SELECT currency, value1, value4, value24, dataTime 
        FROM currency_volatility 
        ORDER BY dataTime DESC 
        LIMIT 300
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $latestData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$latestData) {
        logMessage("没有找到货币波动数据。");
        throw new Exception("没有找到货币波动数据。");
    }

    // 将数据按货币分组
    $currencyData = [];
    foreach ($latestData as $data) {
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
    $stmt = null;
    $db = null;
} catch (PDOException $e) {
    logMessage("数据库错误: " . $e->getMessage());
    die("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    logMessage("系统错误: " . $e->getMessage());
    die("系统错误: " . $e->getMessage());
}

// 将数据转换为 JSON 格式
$currencyDataJson = json_encode($currencyData);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60"> <!-- 每分钟自动刷新 -->
    <title>货币波动曲线图表</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>货币波动曲线图表</h1>

        <!-- 1 小时波动值图表 -->
        <div class="chart-container">
            <h2>最新货币波动强弱指标（1 小时波动值）</h2>
            <canvas id="chartValue1"></canvas>
        </div>

        <!-- 4 小时波动值图表 -->
        <div class="chart-container">
            <h2>最新货币波动强弱指标（4 小时波动值）</h2>
            <canvas id="chartValue4"></canvas>
        </div>

        <!-- 24 小时波动值图表 -->
        <div class="chart-container">
            <h2>最新货币波动强弱指标（24 小时波动值）</h2>
            <canvas id="chartValue24"></canvas>
        </div>
    </div>

    <!-- 引入 Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 获取 PHP 传递的 JSON 数据
        const currencyData = <?php echo $currencyDataJson; ?>;

        // 提取货币名称
        const currencies = Object.keys(currencyData);

        // 获取时间戳作为 X 轴标签
        const labels = currencyData[currencies[0]].map(entry => entry.dataTime);

        // 创建图表数据
        const createDataset = (currency, field, color) => {
            return {
                label: currency,
                data: currencyData[currency].map(entry => entry[field]),
                borderColor: color,
                fill: false,
            };
        };

        // 创建图表
        const createChart = (canvasId, title, field) => {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line', // 曲线图
                data: {
                    labels: labels,
                    datasets: currencies.map(currency => {
                        return createDataset(currency, field, `#${Math.floor(Math.random() * 16777215).toString(16)}`);
                    }),
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: title,
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
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: '波动值',
                            },
                        },
                    },
                },
            });
        };

        // 绘制 1 小时波动值图表
        createChart('chartValue1', '1 小时波动值', 'value1');

        // 绘制 4 小时波动值图表
        createChart('chartValue4', '4 小时波动值', 'value4');

        // 绘制 24 小时波动值图表
        createChart('chartValue24', '24 小时波动值', 'value24');
    </script>
</body>
</html>