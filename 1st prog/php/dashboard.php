<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.html?error=Please login first");
    exit();
}

require_once 'config/db_connection.php';

$role = $_SESSION['users_role'] ?? 'clerk';
$storeId = $_SESSION['store_id'] ?? 0;
$cssPath = '../css/style.css';

// Prepare the last 7 days of sales and order data.
$dateMap = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateMap[$date] = [
        'total_qty' => 0,
        'order_count' => 0,
    ];
}

$chartQuery = "SELECT DATE(sale_date) AS sale_date, SUM(sale_quantity) AS total_qty, COUNT(*) AS order_count FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)";
$result = $connection->query($chartQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $saleDate = $row['sale_date'];
        if (isset($dateMap[$saleDate])) {
            $dateMap[$saleDate]['total_qty'] = (int)$row['total_qty'];
            $dateMap[$saleDate]['order_count'] = (int)$row['order_count'];
        }
    }
}

$chartLabels = [];
$chartSales = [];
$chartOrders = [];
$weeklySales = 0;
$weeklyOrders = 0;
foreach ($dateMap as $date => $values) {
    $chartLabels[] = date('M j', strtotime($date));
    $chartSales[] = $values['total_qty'];
    $chartOrders[] = $values['order_count'];
    $weeklySales += $values['total_qty'];
    $weeklyOrders += $values['order_count'];
}

// AI prediction summary
$predictionCount = 0;
$averageConfidence = 0;
$predictionSummary = $connection->query("SELECT COUNT(*) AS total, AVG(pred_confidence_score) AS avg_confidence FROM ai_prediction");
if ($predictionSummary) {
    $row = $predictionSummary->fetch_assoc();
    $predictionCount = (int)$row['total'];
    $averageConfidence = $row['avg_confidence'] !== null ? round((float)$row['avg_confidence'], 2) : 0;
}

// Low stock count
$lowStockCount = 0;
$lowStockResult = $connection->query("SELECT COUNT(*) AS low_count FROM product WHERE product_quantity <= 10");
if ($lowStockResult) {
    $lowRow = $lowStockResult->fetch_assoc();
    $lowStockCount = (int)$lowRow['low_count'];
}

// Risk level distribution
$riskLabels = [];
$riskCounts = [];
$riskResult = $connection->query("SELECT pred_risk_lvl, COUNT(*) AS count FROM ai_prediction GROUP BY pred_risk_lvl");
if ($riskResult) {
    while ($row = $riskResult->fetch_assoc()) {
        $riskLabels[] = $row['pred_risk_lvl'] ?: 'Unknown';
        $riskCounts[] = (int)$row['count'];
    }
}

// Latest predictions
$latestPredictions = [];
$predictionListQuery = "SELECT pred_id, pred_date, pred_target_period, pred_confidence_score, pred_risk_lvl, pred_suggested_action FROM ai_prediction ORDER BY pred_date DESC LIMIT 5";
$predictionListResult = $connection->query($predictionListQuery);
if ($predictionListResult) {
    while ($row = $predictionListResult->fetch_assoc()) {
        $latestPredictions[] = $row;
    }
}

// Product totals
$productCount = 0;
$productResult = $connection->query("SELECT COUNT(*) AS total FROM product");
if ($productResult) {
    $productRow = $productResult->fetch_assoc();
    $productCount = (int)$productRow['total'];
}

$connection->close();
?>

<?php include '../html/dashboard.html'; ?>
