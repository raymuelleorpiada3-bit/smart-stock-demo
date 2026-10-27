<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.html?error=Please login first");
    exit();
}

$role = $_SESSION['users_role'] ?? 'clerk';
$connection->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Low Stock Alerts</title>
    <link rel="stylesheet" href="../../css/style.css" />
</head>

<body class="admin-body">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-logo">SS</div>
                <div>
                    <h1>Smart Stock AI</h1>
                    <p>System</p>
                </div>
            </div>
            <div class="profile-card">
                <div class="profile-avatar"><?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?></div>
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>
                    <p><?php echo htmlspecialchars(ucfirst($role)); ?></p>
                </div>
            </div>
            <nav class="menu">
                <a class="menu-item" href="../dashboard.php">Dashboard</a>
                <a class="menu-item" href="../products/product_management.php">Product Management</a>
                <a class="menu-item" href="inventory_tracking.php">Stock History</a>
                <a class="menu-item" href="../sales/sales_transactions.php">Sales Transactions</a>
                <a class="menu-item active" href="low_stock.php">Low Stock Alerts</a>
                <a class="menu-item" href="../reports/reports.php">Reports & Analytics</a>
                <a class="menu-item" href="../users/user_management.php">User Management</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <div>
                    <h2>Low Stock Alerts</h2>
                    <p>Monitor products that need restocking.</p>
                </div>
                <form method="POST" action="../auth/logout.php">
                    <button type="submit" class="btn btn-logout">Logout</button>
                </form>
            </header>
            <section class="panel-card">
                <div class="panel-header">
                    <h3>Low Stock Alerts</h3>
                </div>
                <p>This page is ready for future low-stock alert automation.</p>
            </section>
        </main>
    </div>
</body>

</html>
