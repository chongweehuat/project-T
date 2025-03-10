<?php
require_once '/var/www/db/database.php';

// 每页显示的数据条数
$itemsPerPage = 20;

// 获取当前页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// 获取 dataTime 参数
$dataTime = isset($_GET['dataTime']) ? $_GET['dataTime'] : null;

try {
    // 连接数据库
    $db = Database::connect('volatility');

    // 检查数据库连接是否成功
    if (!$db) {
        die("数据库连接失败");
    }

    // 获取总数据条数
    $query = $dataTime ? "SELECT COUNT(*) as total FROM currency_volatility WHERE dataTime = :dataTime" : "SELECT COUNT(*) as total FROM currency_volatility";
    $totalItemsQuery = $db->prepare($query);
    if ($dataTime) {
        $totalItemsQuery->execute([':dataTime' => $dataTime]);
    } else {
        $totalItemsQuery->execute();
    }
    $totalItems = $totalItemsQuery->fetch(PDO::FETCH_ASSOC)['total'];

    // 计算总页数
    $totalPages = ceil($totalItems / $itemsPerPage);

    // 获取当前页的数据
    $query = $dataTime ? "SELECT * FROM currency_volatility WHERE dataTime = :dataTime ORDER BY currency ASC LIMIT :limit OFFSET :offset" : "SELECT * FROM currency_volatility ORDER BY dataTime DESC, currency ASC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($dataTime) {
        $stmt->bindValue(':dataTime', $dataTime, PDO::PARAM_STR);
    }
    $stmt->execute();
    $currencyVolatilityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("数据库错误: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Currency Volatility 数据列表</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            margin: 0 5px;
            padding: 8px 16px;
            background-color: #007bff;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }
        .pagination a:hover {
            background-color: #0056b3;
        }
        .pagination .current {
            background-color: #0056b3;
            color: #fff;
        }
        .pagination .disabled {
            background-color: #ccc;
            pointer-events: none;
        }
        .goto-page {
            margin-top: 20px;
            text-align: center;
        }
        .goto-page input {
            padding: 8px;
            width: 60px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .goto-page button {
            padding: 8px 16px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .goto-page button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1><a href='https://sa.my369.click/?view=currencyStrength' target='currencyStrength'>Currency</a> Volatility 数据列表</h1>
    <?php if ($dataTime): ?>
        <p>数据时间: <?php echo htmlspecialchars($dataTime); ?></p>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>货币</th>
                <th>1 小时波动</th>
                <th>4 小时波动</th>
                <th>24 小时波动</th>
                <th>数据时间</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($currencyVolatilityData)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">没有找到数据。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($currencyVolatilityData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['currency']); ?></td>
                        <td><?php echo htmlspecialchars($row['value1']); ?></td>
                        <td><?php echo htmlspecialchars($row['value4']); ?></td>
                        <td><?php echo htmlspecialchars($row['value24']); ?></td>
                        <td><?php echo htmlspecialchars($row['dataTime']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 分页 -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo $dataTime ? '&dataTime=' . urlencode($dataTime) : ''; ?>">首页</a>
            <a href="?page=<?php echo $page - 1; ?><?php echo $dataTime ? '&dataTime=' . urlencode($dataTime) : ''; ?>">上一页</a>
        <?php else: ?>
            <span class="disabled">首页</span>
            <span class="disabled">上一页</span>
        <?php endif; ?>

        <?php
        // 显示当前页码附近的页码范围
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);

        if ($startPage > 1) {
            echo '<a href="?page=1' . ($dataTime ? '&dataTime=' . urlencode($dataTime) : '') . '">1</a>';
            if ($startPage > 2) {
                echo '<span>...</span>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo $dataTime ? '&dataTime=' . urlencode($dataTime) : ''; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor;

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<span>...</span>';
            }
            echo '<a href="?page=' . $totalPages . ($dataTime ? '&dataTime=' . urlencode($dataTime) : '') . '">' . $totalPages . '</a>';
        }
        ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $dataTime ? '&dataTime=' . urlencode($dataTime) : ''; ?>">下一页</a>
            <a href="?page=<?php echo $totalPages; ?><?php echo $dataTime ? '&dataTime=' . urlencode($dataTime) : ''; ?>">末页</a>
        <?php else: ?>
            <span class="disabled">下一页</span>
            <span class="disabled">末页</span>
        <?php endif; ?>
    </div>

    <!-- 跳转到第 X 页 -->
    <div class="goto-page">
        <form method="GET" action="">
            <?php if ($dataTime): ?>
                <input type="hidden" name="dataTime" value="<?php echo htmlspecialchars($dataTime); ?>">
            <?php endif; ?>
            <input type="number" name="page" min="1" max="<?php echo $totalPages; ?>" placeholder="页码" required>
            <button type="submit">跳转</button>
        </form>
    </div>
</body>
</html>