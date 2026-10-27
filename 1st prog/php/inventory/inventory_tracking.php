<?php
session_start();
if (($_SESSION['logged_in'] ?? false) !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}
require_once '../config/db_connection.php';

$role = $_SESSION['users_role'] ?? 'clerk';
$storeId = (int)($_SESSION['store_id'] ?? 0);
$cssPath = '../../css/style.css';
$isOwner = $role === 'owner';
$allStores = $isOwner && ($_GET['scope'] ?? '') === 'all';
$scope = $allStores ? 'all' : 'mine';
$notices = [];
if (!$allStores && $storeId <= 0) {
    $notices[] = 'Your account needs an assigned store to view stock history.';
}

// Scope product choices and stock to this store unless an owner selects all stores.
$products = [];
$productSql = 'SELECT p.product_id, p.store_id, p.product_name, p.product_barcode,
                      p.product_quantity, s.store_name
               FROM product p LEFT JOIN store s ON s.store_id = p.store_id';
if ($allStores) {
    $productResult = $connection->query($productSql . ' ORDER BY s.store_name, p.product_name, p.product_id');
} else {
    $stmt = $connection->prepare($productSql . ' WHERE p.store_id = ? ORDER BY p.product_name, p.product_id');
    $stmt->bind_param('i', $storeId);
    $stmt->execute();
    $productResult = $stmt->get_result();
}
while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
}
if (isset($stmt)) {
    $stmt->close();
}

$requestedProduct = isset($_GET['product_id']) && is_string($_GET['product_id']) ? $_GET['product_id'] : '';
$productId = filter_var($requestedProduct, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($requestedProduct !== '') {
    $allowedProductIds = array_map('intval', array_column($products, 'product_id'));
    if (!$productId || !in_array($productId, $allowedProductIds, true)) {
        $productId = 0;
        $notices[] = 'That product is not available for this store. Showing all products.';
    }
}
$requestedType = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : '';
$movementType = in_array($requestedType, ['add', 'reduce', 'return'], true) ? $requestedType : '';
$fromDate = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '';
$toDate = isset($_GET['to']) && is_string($_GET['to']) ? $_GET['to'] : '';
foreach (['fromDate' => 'From', 'toDate' => 'To'] as $field => $label) {
    if ($$field !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $$field);
        if (!$date || $date->format('Y-m-d') !== $$field) {
            $$field = '';
            $notices[] = $label . ' date is invalid; that filter was ignored.';
        }
    }
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $toDate = '';
    $notices[] = 'The end date was before the start date; the end date was ignored.';
}

// Present stock is live. Date and movement filters affect the history only.
$stockProducts = $productId
    ? array_values(array_filter($products, fn($row) => (int)$row['product_id'] === $productId))
    : $products;
$totalStock = array_sum(array_map(fn($row) => (int)$row['product_quantity'], $stockProducts));

$where = [];
$values = [];
$types = '';
if (!$allStores) {
    $where[] = 'm.store_id = ?';
    $values[] = $storeId;
    $types .= 'i';
}
if ($productId) {
    $where[] = 'm.product_id = ?';
    $values[] = $productId;
    $types .= 'i';
}
if ($movementType !== '') {
    $where[] = 'm.movement_type = ?';
    $values[] = $movementType;
    $types .= 's';
}
if ($fromDate !== '') {
    $where[] = 'm.movement_date >= ?';
    $values[] = $fromDate . ' 00:00:00';
    $types .= 's';
}
if ($toDate !== '') {
    $where[] = 'm.movement_date < ?';
    $values[] = (new DateTimeImmutable($toDate))->modify('+1 day')->format('Y-m-d 00:00:00');
    $types .= 's';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $connection->prepare('SELECT COUNT(*) AS total FROM stock_movement m' . $whereSql);
if ($types !== '') {
    $countStmt->bind_param($types, ...$values);
}
$countStmt->execute();
$movementCount = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pageSize = 25;
$pageCount = max(1, (int)ceil($movementCount / $pageSize));
$requestedPage = isset($_GET['page']) && is_string($_GET['page'])
    ? filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$page = $requestedPage ? min($requestedPage, $pageCount) : 1;
$offset = ($page - 1) * $pageSize;

$movementSql = 'SELECT m.movement_id, m.product_id, m.store_id, m.users_id,
                       m.movement_type, m.quantity, m.previous_quantity, m.new_quantity,
                       m.notes, m.movement_date, p.product_name, s.store_name,
                       u.users_fname, u.users_lname
                FROM stock_movement m
                LEFT JOIN product p ON p.product_id = m.product_id AND p.store_id = m.store_id
                LEFT JOIN store s ON s.store_id = m.store_id
                LEFT JOIN users u ON u.users_id = m.users_id'
             . $whereSql . ' ORDER BY m.movement_date DESC, m.movement_id DESC LIMIT ? OFFSET ?';
$movementStmt = $connection->prepare($movementSql);
$listValues = array_merge($values, [$pageSize, $offset]);
$movementStmt->bind_param($types . 'ii', ...$listValues);
$movementStmt->execute();
$movements = $movementStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$movementStmt->close();
$connection->close();

// Keep only validated filters in Previous / Next links.
$pageParams = ['scope' => $scope];
if ($productId) $pageParams['product_id'] = $productId;
if ($movementType !== '') $pageParams['type'] = $movementType;
if ($fromDate !== '') $pageParams['from'] = $fromDate;
if ($toDate !== '') $pageParams['to'] = $toDate;

include '../../html/inventory_tracking.html';
