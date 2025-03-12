<?php
require_once '/var/www/db/database.php';
require_once '/var/www/utils/Logger.php';

//$debug=true;

// 接收POST数据
$symbol = $_POST['symbol'];
$price = floatval($_POST['price']); // 当前价格
$dataTime = str_replace('.', '-', $_POST['dataTime']); // 时间戳

// 记录接收到的POST数据
logMessage("Received POST data: " . var_export($_POST, true));
logMessage("dataTime=" . $dataTime);

try {
    // 连接数据库
    $db = Database::connect('volatility'); 

    // 检查数据库连接是否成功
    if (!$db) {
        logMessage("数据库连接失败");
        throw new Exception("数据库连接失败");
    }

    // 准备 SQL 语句
    $sql = "
        INSERT INTO price_data (
            symbol, price, dataTime
        ) VALUES (
            :symbol, :price, :dataTime
        )
        ON DUPLICATE KEY UPDATE
            price = VALUES(price)
    ";

    // 记录 SQL 语句
    logMessage("SQL: " . $sql);

    // 准备 SQL 语句
    $stmt = $db->prepare($sql);

    // 检查 SQL 语句是否准备成功
    if (!$stmt) {
        logMessage("SQL 语句准备失败: " . print_r($db->errorInfo(), true));
        throw new Exception("SQL 语句准备失败");
    }

    // 执行 SQL 语句
    $result = $stmt->execute([
        ':symbol' => $symbol,
        ':price' => $price,
        ':dataTime' => $dataTime
    ]);

    // 检查 SQL 执行结果
    if ($result) {
        logMessage("数据插入成功: symbol=$symbol, price=$price, dataTime=" . date('Y-m-d H:i:s', strtotime($dataTime)));

        // 检查是否已收集到所有28个货币对的数据
        $checkCompletenessSql = "SELECT COUNT(*) AS total FROM price_data WHERE dataTime = :dataTime";
        $stmt = $db->prepare($checkCompletenessSql);
        $stmt->execute([':dataTime' => $dataTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['total'] >= 28) {
            // 调用计算接口（如果需要）
            $calculateUrl = "https://sapi.my369.click/calculate_currency_prices.php?dataTime=" . urlencode($dataTime);
            $ch = curl_init($calculateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            logMessage("$dataTime - Calculation endpoint response: " . $response);
        }
    } else {
        logMessage("数据插入失败: symbol=$symbol, price=$price, dataTime=" . date('Y-m-d H:i:s', strtotime($dataTime)));
        logMessage("SQL 错误信息: " . print_r($stmt->errorInfo(), true));
    }

    // 释放资源
    $stmt = null;
    $db = null;

    // 返回成功响应
    echo json_encode(["status" => "success", "message" => "数据插入成功"]);
} catch (Exception $e) {
    // 记录错误日志
    logMessage("发生异常: " . $e->getMessage());

    // 返回错误响应
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}