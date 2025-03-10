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

    // 获取最新的多个货币波动数据
    $sql = "
        SELECT currency, value1, value4, value24, dataTime 
        FROM currency_volatility 
        WHERE dataTime = (SELECT MAX(dataTime) FROM currency_volatility)
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $latestData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$latestData) {
        logMessage("没有找到货币波动数据。");
        throw new Exception("没有找到货币波动数据。");
    }

    // 获取最新数据的时间
    $dataTime = $latestData[0]['dataTime'];

    // 按 1 小时波动值排序（从小到大）
    usort($latestData, function ($a, $b) {
        return $a['value1'] <=> $b['value1']; // 升序排序
    });
    $sortedByValue1 = $latestData;

    // 按 4 小时波动值排序（从小到大）
    usort($latestData, function ($a, $b) {
        return $a['value4'] <=> $b['value4']; // 升序排序
    });
    $sortedByValue4 = $latestData;

    // 按 24 小时波动值排序（从小到大）
    usort($latestData, function ($a, $b) {
        return $a['value24'] <=> $b['value24']; // 升序排序
    });
    $sortedByValue24 = $latestData;

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

/**
 * 生成表格行
 * @param array $data 数据
 * @param string $field 排序字段（value1, value4, value24）
 * @return string 表格行 HTML
 */
function generateTableRow($data, $field) {
    $row = "<tr><td class='data-label'>{$field}</td>";
    foreach ($data as $currencyData) {
        $row .= "<td>" . htmlspecialchars(number_format(100 * $currencyData[$field])) . "</td>";
    }
    $row .= "</tr>";
    return $row;
}

/**
 * 生成货币表头
 * @param array $data 数据
 * @return string 表头 HTML
 */
function generateTableHeader($data) {
    $header = "<tr><th>货币</th>";
    foreach ($data as $currencyData) {
        $header .= "<th>" . htmlspecialchars($currencyData['currency']) . "</th>";
    }
    $header .= "</tr>";
    return $header;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>最新货币波动强弱指标</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        .data-label {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><a href='https://sa.my369.click/?view=currencyStrengthChart' target=_blank>最新货币波动强弱指标</a> <?php echo htmlspecialchars($dataTime); ?></h1>

        <!-- 1 小时波动值排序 -->
        <h2>1 小时波动值排序</h2>
        <table>
            <?php echo generateTableHeader($sortedByValue1); ?>
            <tbody>
                <?php echo generateTableRow($sortedByValue1, 'value1'); ?>
            </tbody>
        </table>

        <!-- 4 小时波动值排序 -->
        <h2>4 小时波动值排序</h2>
        <table>
            <?php echo generateTableHeader($sortedByValue4); ?>
            <tbody>
                <?php echo generateTableRow($sortedByValue4, 'value4'); ?>
            </tbody>
        </table>

        <!-- 24 小时波动值排序 -->
        <h2>24 小时波动值排序</h2>
        <table>
            <?php echo generateTableHeader($sortedByValue24); ?>
            <tbody>
                <?php echo generateTableRow($sortedByValue24, 'value24'); ?>
            </tbody>
        </table>
    </div>
</body>
</html>