<?php
// html/index.php

require_once 'controllers/OrderController.php';
require_once 'config/database.php'; // Database connection

$controller = new OrderController($pdo);

if (isset($_GET['action']) && $_GET['action'] === 'placeOrder') {
    $controller->placeOrder();
} else {
    $controller->index();
}
?>
