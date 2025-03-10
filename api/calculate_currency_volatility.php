<?php
require_once '/var/www/db/database.php';
require_once '/var/www/utils/Logger.php';

// 8 个主要货币
$majorCurrencies = ['EUR', 'USD', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'NZD'];

/**
 * 计算主要货币的相对波动值并存储到数据库
 *
 * @param PDO $db 数据库连接
 * @param string $dataTime 需要计算的时间点
 */
function calculateAndStoreCurrencyVolatilityForTime($db, $dataTime) {
    global $majorCurrencies;

    // 获取指定时间点的所有货币对数据
    $stmt = $db->prepare("SELECT symbol, value1, value4, value24, avg_value1, avg_value4, avg_value24 FROM volatility WHERE dataTime = :dataTime");
    $stmt->execute([':dataTime' => $dataTime]);
    $volatilityData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($volatilityData) === 28) {
        // 计算并存储主要货币的相对波动值
        calculateForTimePoint($db, $volatilityData, $dataTime);
        logMessage("货币波动指数计算成功: dataTime=$dataTime");
        return true;
    } else {
        logMessage("时间点 $dataTime 的数据不完整，期望 28 个货币对，实际找到 " . count($volatilityData) . " 个。");
        throw new Exception("时间点 $dataTime 的数据不完整。");
    }
}

/**
 * 计算某个时间点的主要货币相对波动值并存储到数据库
 *
 * @param PDO $db 数据库连接
 * @param array $volatilityData 某个时间点的波动数据
 * @param string $dataTime 数据时间
 */
function calculateForTimePoint($db, $volatilityData, $dataTime) {
    global $majorCurrencies;

    // 计算每个主要货币的相对波动值
    $majorVolatility = [];
    foreach ($majorCurrencies as $currency) {
        $majorVolatility[$currency] = [
            'value1' => 0,
            'value4' => 0,
            'value24' => 0,
            'count' => 0
        ];
    }

    foreach ($volatilityData as $row) {
        $symbol = $row['symbol'];
        $baseCurrency = substr($symbol, 0, 3); // 基础货币，例如 EURUSD 的 EUR
        $quoteCurrency = substr($symbol, 3, 3); // 报价货币，例如 EURUSD 的 USD

        // 计算相对波动值
        $relativeValue1 = $row['value1'] / $row['avg_value1'];
        $relativeValue4 = $row['value4'] / $row['avg_value4'];
        $relativeValue24 = $row['value24'] / $row['avg_value24'];

        // 更新基础货币的相对波动值
        if (isset($majorVolatility[$baseCurrency])) {
            $majorVolatility[$baseCurrency]['value1'] += $relativeValue1;
            $majorVolatility[$baseCurrency]['value4'] += $relativeValue4;
            $majorVolatility[$baseCurrency]['value24'] += $relativeValue24;
            $majorVolatility[$baseCurrency]['count']++;
        }

        // 更新报价货币的相对波动值
        if (isset($majorVolatility[$quoteCurrency])) {
            $majorVolatility[$quoteCurrency]['value1'] -= $relativeValue1;
            $majorVolatility[$quoteCurrency]['value4'] -= $relativeValue4;
            $majorVolatility[$quoteCurrency]['value24'] -= $relativeValue24;
            $majorVolatility[$quoteCurrency]['count']++;
        }
    }

    // 计算平均值并插入到新表
    foreach ($majorVolatility as $currency => $data) {
        if ($data['count'] > 0) {
            $value1 = $data['value1'] / $data['count'];
            $value4 = $data['value4'] / $data['count'];
            $value24 = $data['value24'] / $data['count'];

            // 插入到 currency_volatility 表
            $stmt = $db->prepare("
                INSERT INTO currency_volatility (
                    currency, value1, value4, value24, dataTime
                ) VALUES (
                    :currency, :value1, :value4, :value24, :dataTime
                )
                ON DUPLICATE KEY UPDATE
                    value1 = VALUES(value1),
                    value4 = VALUES(value4),
                    value24 = VALUES(value24)
            ");
            $result = $stmt->execute([
                ':currency' => $currency,
                ':value1' => $value1,
                ':value4' => $value4,
                ':value24' => $value24,
                ':dataTime' => $dataTime
            ]);

            // 检查 SQL 执行结果
            if ($result) {
                logMessage("数据插入成功: currency=$currency, value1=$value1, value4=$value4, value24=$value24, dataTime=$dataTime");
            } else {
                logMessage("数据插入失败: currency=$currency, value1=$value1, value4=$value4, value24=$value24, dataTime=$dataTime");
                logMessage("SQL 错误信息: " . print_r($stmt->errorInfo(), true));
            }
        }
    }
}

try {
    // 连接数据库
    $db = Database::connect('volatility');

    // 检查数据库连接是否成功
    if (!$db) {
        logMessage("数据库连接失败");
        throw new Exception("数据库连接失败");
    }

    // 解析 URL 参数
    $dataTime = $_GET['dataTime'] ?? null;

    if ($dataTime) {
        // 处理单个时间点
        $dataTime = urldecode($dataTime); // 解码 URL 编码的时间参数
        $dataTime = str_replace('+', ' ', $dataTime); // 将 + 替换为空格
        $dataTime = date('Y-m-d H:i:s', strtotime($dataTime)); // 确保时间格式正确

        // 计算并存储货币波动指数
        calculateAndStoreCurrencyVolatilityForTime($db, $dataTime);

        // 返回成功响应
        echo json_encode(["status" => "success", "message" => "Currency volatility data calculated and stored successfully for dataTime=$dataTime."]);
    } else {
        // 如果没有提供 dataTime 参数
        logMessage("未提供 dataTime 参数。");
        throw new Exception("未提供 dataTime 参数。");
    }

    // 释放资源
    $db = null;
} catch (PDOException $e) {
    // 记录错误日志
    logMessage("数据库错误: " . $e->getMessage());

    // 返回错误响应
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    // 记录错误日志
    logMessage("系统错误: " . $e->getMessage());

    // 返回错误响应
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
    exit;
}
?>