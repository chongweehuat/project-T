<?php
require_once '/var/www/db/database.php';
require_once '/var/www/utils/Logger.php';

// 连接数据库
$db = Database::connect('volatility');

// 检查数据库连接是否成功
if (!$db) {
    die("数据库连接失败");
}

// 获取需要补全的时间点和补全来源时间
if (isset($_GET['dataTime']) && isset($_GET['prevTime'])) {
    $dataTime = $_GET['dataTime'];
    $prevTime = $_GET['prevTime'];

    // 获取补全来源时间的所有配对数据
    $prevDataStmt = $db->prepare("SELECT symbol, value1, value4, value24, avg_value1, avg_value4, avg_value24 FROM volatility WHERE dataTime = :prevTime");
    $prevDataStmt->execute([':prevTime' => $prevTime]);
    $prevData = $prevDataStmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取当前时间点的已有配对
    $currentDataStmt = $db->prepare("SELECT symbol FROM volatility WHERE dataTime = :dataTime");
    $currentDataStmt->execute([':dataTime' => $dataTime]);
    $currentSymbols = $currentDataStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // 填补空缺
    $filledCount = 0;
    foreach ($prevData as $row) {
        if (!in_array($row['symbol'], $currentSymbols)) {
            // 如果当前时间点没有该配对，则插入
            $insertStmt = $db->prepare("
                INSERT INTO volatility (
                    symbol, value1, value4, value24, 
                    avg_value1, avg_value4, avg_value24, dataTime
                ) VALUES (
                    :symbol, :value1, :value4, :value24, 
                    :avg_value1, :avg_value4, :avg_value24, :dataTime
                )
            ");
            $insertStmt->execute([
                ':symbol' => $row['symbol'],
                ':value1' => $row['value1'],
                ':value4' => $row['value4'],
                ':value24' => $row['value24'],
                ':avg_value1' => $row['avg_value1'],
                ':avg_value4' => $row['avg_value4'],
                ':avg_value24' => $row['avg_value24'],
                ':dataTime' => $dataTime
            ]);
            $filledCount++;
        }
    }

    echo "数据补全成功：$dataTime ，填补了 $filledCount 个配对（补全来源时间：$prevTime ）。";
} else {
    echo "未指定需要补全的时间点或补全来源时间。";
}