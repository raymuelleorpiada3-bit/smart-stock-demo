<?php
session_start();

if (($_SESSION['logged_in'] ?? false) !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sales_transactions.php');
    exit();
}

function goBack(string $kind, string $message): void {
    header('Location: sales_transactions.php?' . $kind . '=' . urlencode($message));
    exit();
}

$csrf = $_POST['sale_csrf'] ?? '';
if (!isset($_SESSION['sale_csrf']) || !is_string($csrf) || $csrf === '' ||
    !hash_equals($_SESSION['sale_csrf'], $csrf)) {
    goBack('error', 'The form expired. Reload the page and try again.');
}

$barcode = trim($_POST['product_barcode'] ?? '');
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
$storeId = (int)($_SESSION['store_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    goBack('error', 'Your session has expired. Sign out and sign in again.');
}

if ($storeId <= 0 || !preg_match('/^[A-Za-z0-9_-]{3,50}$/', $barcode) ||
    !is_int($quantity) || $quantity < 1 || $quantity > 10000) {
    goBack('error', 'Choose a product barcode and quantity between 1 and 10000.');
}

require_once '../config/db_connection.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $connection->begin_transaction();

    // The movement history must identify a current user at this store.
    $stmt = $connection->prepare(
        'SELECT users_id FROM users WHERE users_id = ? AND store_id = ?'
    );
    $stmt->bind_param('ii', $userId, $storeId);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$actor) {
        throw new DomainException('Your account or store has changed. Sign out and sign in again.');
    }

    // Lock this store's product so two simultaneous sales cannot oversell it.
    $stmt = $connection->prepare(
        'SELECT product_id, product_name, product_quantity
         FROM product WHERE store_id = ? AND product_barcode = ? FOR UPDATE'
    );
    $stmt->bind_param('is', $storeId, $barcode);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        throw new DomainException('No product with that barcode exists at your store.');
    }
    $previousQuantity = (int)$product['product_quantity'];
    if ($previousQuantity < $quantity) {
        throw new DomainException('Insufficient stock for this sale.');
    }

    $productId = (int)$product['product_id'];
    $newQuantity = $previousQuantity - $quantity;
    $stmt = $connection->prepare(
        'UPDATE product SET product_quantity = product_quantity - ? WHERE product_id = ?'
    );
    $stmt->bind_param('ii', $quantity, $productId);
    $stmt->execute();
    $stmt->close();

    $stmt = $connection->prepare(
        'INSERT INTO sales (store_id, product_id, sale_quantity) VALUES (?, ?, ?)'
    );
    $stmt->bind_param('iii', $storeId, $productId, $quantity);
    $stmt->execute();
    $saleId = (int)$connection->insert_id;
    $stmt->close();

    $notes = $saleId > 0 ? 'Completed sale #' . $saleId : 'Completed sale';
    $stmt = $connection->prepare(
        "INSERT INTO stock_movement
         (product_id, store_id, users_id, movement_type, quantity,
          previous_quantity, new_quantity, notes)
         VALUES (?, ?, ?, 'reduce', ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'iiiiiis', $productId, $storeId, $userId, $quantity,
        $previousQuantity, $newQuantity, $notes
    );
    $stmt->execute();
    $stmt->close();

    $connection->commit();
    $connection->close();
    // Prevent a browser Back/refresh from submitting the same sale a second time.
    $_SESSION['sale_csrf'] = bin2hex(random_bytes(32));
    goBack('message', 'Sale recorded for ' . $product['product_name'] . '.');
} catch (DomainException $error) {
    $connection->rollback();
    $connection->close();
    goBack('error', $error->getMessage());
} catch (Throwable $error) {
    $connection->rollback();
    $connection->close();
    error_log('Sale recording failed: ' . $error->getMessage());
    goBack('error', 'Sale was not recorded. Check the migration and server logs.');
}
