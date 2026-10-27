<?php
session_start();

if (($_SESSION['logged_in'] ?? false) !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}
if (($_SESSION['users_role'] ?? '') !== 'owner') {
    header('Location: product_management.php?error=Only the owner can add products');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: product_management.php?error=Invalid request');
    exit();
}
if (!isset($_SESSION['product_csrf']) ||
    !is_string($_POST['product_csrf'] ?? null) ||
    !hash_equals($_SESSION['product_csrf'], $_POST['product_csrf'])) {
    header('Location: product_management.php?error=' . urlencode('The form expired. Reload the page and try again.'));
    exit();
}

$productName = trim($_POST['product_name'] ?? '');
$productBarcode = trim($_POST['product_barcode'] ?? '');
$productQuantity = filter_var($_POST['product_quantity'] ?? null, FILTER_VALIDATE_INT);
$storeId = (int)($_SESSION['store_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($productName === '' || strlen($productName) > 100 ||
    !preg_match('/^[A-Za-z0-9_-]{3,50}$/', $productBarcode) ||
    !is_int($productQuantity) || $productQuantity <= 0 ||
    $productQuantity > 10000 || $storeId <= 0 || $userId <= 0) {
    header('Location: product_management.php?error=' . urlencode(
        'Provide a product name, barcode (3-50 letters or digits), and quantity from 1 to 10000.'
    ));
    exit();
}

require_once '../config/db_connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection->begin_transaction();

    $stmt = $connection->prepare('SELECT users_role FROM users WHERE users_id = ? AND store_id = ?');
    $stmt->bind_param('ii', $userId, $storeId);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$actor || $actor['users_role'] !== 'owner') {
        throw new DomainException('Your account or store has changed. Sign out and sign in again.');
    }

    $stmt = $connection->prepare(
        'INSERT INTO product (store_id, product_name, product_barcode, product_quantity, product_date_added)
         VALUES (?, ?, ?, ?, CURDATE())'
    );
    $stmt->bind_param('issi', $storeId, $productName, $productBarcode, $productQuantity);
    $stmt->execute();
    $productId = (int)$connection->insert_id;
    $stmt->close();

    $previousQuantity = 0;
    $notes = 'Initial stock when product was created';
    $stmt = $connection->prepare(
        "INSERT INTO stock_movement
         (product_id, store_id, users_id, movement_type, quantity,
          previous_quantity, new_quantity, notes)
         VALUES (?, ?, ?, 'add', ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iiiiiis', $productId, $storeId, $userId, $productQuantity,
        $previousQuantity, $productQuantity, $notes
    );
    $stmt->execute();
    $stmt->close();

    $connection->commit();
    $connection->close();
    header('Location: product_management.php?message=' . urlencode('Product added successfully'));
    exit();
} catch (DomainException $error) {
    $connection->rollback();
    $connection->close();
    header('Location: product_management.php?error=' . urlencode($error->getMessage()));
    exit();
} catch (Throwable $error) {
    $connection->rollback();
    $connection->close();
    error_log('Product creation failed: ' . $error->getMessage());
    $message = $error instanceof mysqli_sql_exception && $error->getCode() === 1062
        ? 'This barcode already belongs to a product at your store'
        : 'Could not add product. Check the database migration and server logs.';
    header('Location: product_management.php?error=' . urlencode($message));
    exit();
}
