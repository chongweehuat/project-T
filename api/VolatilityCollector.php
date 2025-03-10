<?php
require_once '/var/www/db/database.php';
require_once '/var/www/utils/Logger.php';

//$debug = true;

// 接收POST数据
$symbol = $_POST['symbol'];
$value1 = floatval($_POST['value1']); // H1 当前波幅（带符号）
$value4 = floatval($_POST['value4']); // H4 当前波幅（带符号）
$value24 = floatval($_POST['value24']); // D1 当前波幅（带符号）
$avg_value1 = floatval($_POST['avg_value1']); // H1 平均波幅（绝对值）
$avg_value4 = floatval($_POST['avg_value4']); // H4 平均波幅（绝对值）
$avg_value24 = floatval($_POST['avg_value24']); // D1 平均波幅（绝对值）
$dataTime = str_replace('.', '-', $_POST['dataTime']);

// 记录接收到的POST数据
//logMessage("Received POST data: " . var_export($_POST, true));
//logMessage("dataTime=" . $dataTime);
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
        INSERT INTO volatility (
            symbol, value1, value4, value24, 
            avg_value1, avg_value4, avg_value24, dataTime
        ) VALUES (
            :symbol, :value1, :value4, :value24, 
            :avg_value1, :avg_value4, :avg_value24, :dataTime
        )
        ON DUPLICATE KEY UPDATE
            value1 = VALUES(value1),
            value4 = VALUES(value4),
            value24 = VALUES(value24),
            avg_value1 = VALUES(avg_value1),
            avg_value4 = VALUES(avg_value4),
            avg_value24 = VALUES(avg_value24)
    ";

    // 记录 SQL 语句
    //logMessage("SQL: " . $sql);

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
        ':value1' => $value1,
        ':value4' => $value4,
        ':value24' => $value24,
        ':avg_value1' => $avg_value1,
        ':avg_value4' => $avg_value4,
        ':avg_value24' => $avg_value24,
        ':dataTime' => $dataTime
    ]);

    // 检查 SQL 执行结果
    if ($result) {
        logMessage("数据插入成功: symbol=$symbol, value1=$value1, value4=$value4, value24=$value24, avg_value1=$avg_value1, avg_value4=$avg_value4, avg_value24=$avg_value24, dataTime=" . date('Y-m-d H:i:s', strtotime($dataTime)));
   
        $checkCompletenessSql = "SELECT COUNT(*) AS total FROM volatility WHERE dataTime = :dataTime";
        $stmt = $db->prepare($checkCompletenessSql);
        $stmt->execute([':dataTime' => $dataTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['total'] >= 28) {
            // 调用计算接口
            $calculateUrl = "https://sapi.my369.click/calculate_currency_volatility.php?dataTime=" . urlencode($dataTime);
            $ch = curl_init($calculateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            logMessage("$dataTime - Calculation endpoint response: " . $response);
        }


     } else {
        logMessage("数据插入失败: symbol=$symbol, value1=$value1, value4=$value4, value24=$value24, avg_value1=$avg_value1, avg_value4=$avg_value4, avg_value24=$avg_value24, dataTime=" . date('Y-m-d H:i:s', strtotime($dataTime)));
        logMessage("SQL 错误信息: " . print_r($stmt->errorInfo(), true));
    }

    // 释放资源
    $stmt = null;
    $db = null;

    // 返回成功响应
    echo json_encode(["status" => "success", "message" => "Data inserted successfully."]);
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