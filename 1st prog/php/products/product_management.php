<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}

$role = $_SESSION['users_role'] ?? 'clerk';
$storeId = $_SESSION['store_id'] ?? 0;
if (!isset($_SESSION['product_csrf'])) {
    $_SESSION['product_csrf'] = bin2hex(random_bytes(32));
}
$message = isset($_GET['message']) ? $_GET['message'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
$cssPath = '../../css/style.css';

$products = [];
$query = "SELECT p.product_id, p.store_id, p.product_name, p.product_barcode,
                 p.product_quantity, p.product_date_added, s.store_name,
                 EXISTS (SELECT 1 FROM sales sa WHERE sa.product_id = p.product_id) AS has_sales,
                 EXISTS (SELECT 1 FROM stock_movement sm WHERE sm.product_id = p.product_id) AS has_movements
          FROM product p LEFT JOIN store s ON s.store_id = p.store_id";
if ($role === 'clerk') {
    $stmt = $connection->prepare($query . ' WHERE p.store_id = ? ORDER BY p.product_id DESC');
    $filterStoreId = (int)$storeId;
    $stmt->bind_param('i', $filterStoreId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $connection->query($query . ' ORDER BY p.product_id DESC');
}
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
if (isset($stmt)) {
    $stmt->close();
}

$reduceProducts = $role === 'clerk'
    ? $products
    : array_values(array_filter($products, fn($row) => (int)$row['store_id'] === (int)$storeId));

$connection->close();

include '../../html/product_management.html';
?>
