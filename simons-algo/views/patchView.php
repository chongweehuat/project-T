<?php
require_once '/var/www/db/database.php';
require_once '/var/www/utils/Logger.php';

// 连接数据库
$db = Database::connect('volatility');

// 检查数据库连接是否成功
if (!$db) {
    die("数据库连接失败");
}

// 初始化时间变量
$startTime = '';
$endTime = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['startTime']) && isset($_POST['endTime'])) {
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];

    // 获取时间段内的数据统计
    $stmt = $db->prepare("
        SELECT dataTime, COUNT(*) as count 
        FROM volatility 
        WHERE dataTime BETWEEN :startTime AND :endTime 
        GROUP BY dataTime
        ORDER BY dataTime
    ");
    $stmt->execute([':startTime' => $startTime, ':endTime' => $endTime]);
    $timeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取每个时间点的上一笔记录时间
    $prevTimes = [];
    foreach ($timeStats as $stat) {
        $prevTimeStmt = $db->prepare("SELECT MAX(dataTime) as prevTime FROM volatility WHERE dataTime < :dataTime");
        $prevTimeStmt->execute([':dataTime' => $stat['dataTime']]);
        $prevTime = $prevTimeStmt->fetch(PDO::FETCH_ASSOC)['prevTime'];
        $prevTimes[$stat['dataTime']] = $prevTime;
    }

    // 检查每个时间点的货币波动指数是否存在
    $volatilityCheck = [];
    foreach ($timeStats as $stat) {
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM currency_volatility WHERE dataTime = :dataTime");
        $checkStmt->execute([':dataTime' => $stat['dataTime']]);
        $volatilityCheck[$stat['dataTime']] = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }

    // 显示统计结果
    echo "<h2>时间段内的数据统计</h2>";
    echo "<table border='1'>";
    echo "<tr><th>时间点</th><th>配对数量</th><th>补全来源时间</th><th>货币波动指数</th><th>操作</th></tr>";
    foreach ($timeStats as $stat) {
        echo "<tr>";
        echo "<td>" . $stat['dataTime'] . "</td>";
        echo "<td>" . $stat['count'] . "</td>";
        echo "<td>" . ($prevTimes[$stat['dataTime']] ?? '无') . "</td>";

        // 货币波动指数列
        if ($volatilityCheck[$stat['dataTime']]) {
            echo "<td>已计算</td>";
        } else {
            echo "<td><a href='https://sapi.my369.click/calculate_currency_volatility.php?dataTime=" . urlencode($stat['dataTime']) . "' target='_blank'>计算</a></td>";
        }

        // 操作列
        if ($stat['count'] < 28 && isset($prevTimes[$stat['dataTime']])) {
            echo "<td><a href='https://sapi.my369.click/complete_data.php?dataTime=" . urlencode($stat['dataTime']) . "&prevTime=" . urlencode($prevTimes[$stat['dataTime']]) . "' target='_blank'>补全</a></td>";
        } else {
            echo "<td>完整</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>数据补全功能</title>
</head>
<body>
    <h1>数据补全功能</h1>
    <form method="POST" action="">
        <label for="startTime">开始时间：</label>
        <input type="datetime-local" id="startTime" name="startTime" value="<?php echo htmlspecialchars($startTime); ?>" required>
        <br>
        <label for="endTime">结束时间：</label>
        <input type="datetime-local" id="endTime" name="endTime" value="<?php echo htmlspecialchars($endTime); ?>" required>
        <br>
        <button type="submit">查询</button>
    </form>
</body>
</html>