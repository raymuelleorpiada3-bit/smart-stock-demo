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
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

if ($storeId <= 0) {
    $error = 'Your account needs a store before recording sales';
}

if (!isset($_SESSION['sale_csrf'])) {
    $_SESSION['sale_csrf'] = bin2hex(random_bytes(32));
}

$products = [];
$recentSales = [];
$analysisById = [];
$signalsById = [];
$analysisStatus = '';
$topSellers = [];

if ($storeId > 0) {
    $stmt = $connection->prepare(
        'SELECT product_id, product_name, product_barcode, product_quantity
         FROM product WHERE store_id = ? ORDER BY product_name'
    );
    $stmt->bind_param('i', $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();

    $stmt = $connection->prepare(
        'SELECT s.sales_id, s.sale_date, s.sale_quantity, p.product_name, p.product_barcode
         FROM sales s JOIN product p ON p.product_id = s.product_id
         WHERE s.store_id = ? ORDER BY s.sale_date DESC, s.sales_id DESC LIMIT 25'
    );
    $stmt->bind_param('i', $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentSales[] = $row;
    }
    $stmt->close();

    $stmt = $connection->prepare(
        'SELECT p.product_id, p.product_name, p.product_barcode,
                COALESCE(p.product_quantity, 0) AS current_stock,
                GREATEST(0, DATEDIFF(CURDATE(), COALESCE(p.product_date_added, CURDATE()))) AS age_days,
                COALESCE(SUM(CASE WHEN s.sale_date >= NOW() - INTERVAL 30 DAY
                                  THEN s.sale_quantity ELSE 0 END), 0) AS sold_30d,
                COALESCE(SUM(CASE WHEN s.sale_date >= NOW() - INTERVAL 90 DAY
                                  THEN s.sale_quantity ELSE 0 END), 0) AS sold_90d,
                DATEDIFF(CURDATE(), MAX(s.sale_date)) AS days_since_last_sale
         FROM product p
         LEFT JOIN sales s ON s.product_id = p.product_id AND s.store_id = p.store_id
         WHERE p.store_id = ?
         GROUP BY p.product_id, p.product_name, p.product_barcode,
                  p.product_quantity, p.product_date_added
         ORDER BY p.product_name'
    );
    $stmt->bind_param('i', $storeId);
    $stmt->execute();
    $signals = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $signals[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'current_stock' => (int)$row['current_stock'],
            'sold_30d' => (int)$row['sold_30d'],
            'sold_90d' => (int)$row['sold_90d'],
            'age_days' => (int)$row['age_days'],
            'days_since_last_sale' => $row['days_since_last_sale'] === null
                ? null : max(0, (int)$row['days_since_last_sale']),
        ];
    }
    $stmt->close();

    foreach ($signals as $signal) {
        $signalsById[$signal['product_id']] = $signal;
    }

    // Best sellers come from completed sales; no Python service is needed for counts.
    $topSellers = array_values(array_filter($signals, fn($row) => $row['sold_30d'] > 0));
    usort($topSellers, fn($a, $b) => $b['sold_30d'] <=> $a['sold_30d']);
    $topSellers = array_slice($topSellers, 0, 5);

    if ($signals) {
        if (!function_exists('curl_init')) {
            $analysisStatus = 'Enable PHP cURL to view the Python baseline analysis.';
        } else {
            $request = curl_init('http://127.0.0.1:8000/analyze');
            curl_setopt_array($request, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['products' => $signals]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 3,
            ]);
            $response = curl_exec($request);
            $httpCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
            curl_close($request);

            $decoded = is_string($response) ? json_decode($response, true) : null;
            if ($httpCode === 200 && isset($decoded['products']) && is_array($decoded['products'])) {
                foreach ($decoded['products'] as $item) {
                    $analysisById[(int)$item['product_id']] = $item;
                }
            } else {
                $analysisStatus = 'Start the included fastapi-service, then refresh to see stock projections.';
            }
        }
    }
}

$connection->close();
include '../../html/sales_transactions.html';
