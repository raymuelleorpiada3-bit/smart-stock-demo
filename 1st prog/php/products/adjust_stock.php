<?php
session_start();

if (($_SESSION['logged_in'] ?? false) !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}

function backToProducts(string $kind, string $message): void {
    header('Location: product_management.php?' . $kind . '=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    backToProducts('error', 'Invalid request');
}
if (!isset($_SESSION['product_csrf']) ||
    !is_string($_POST['product_csrf'] ?? null) ||
    !hash_equals($_SESSION['product_csrf'], $_POST['product_csrf'])) {
    backToProducts('error', 'The form expired. Reload the page and try again.');
}

$role = $_SESSION['users_role'] ?? '';
$storeId = (int)($_SESSION['store_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
$notes = isset($_POST['notes']) && is_string($_POST['notes']) ? trim($_POST['notes']) : '';

if (!in_array($role, ['owner', 'clerk'], true) || $storeId <= 0 || $userId <= 0) {
    backToProducts('error', 'Your session has expired. Sign out and sign in again.');
}
if (!is_int($productId) || $productId <= 0 ||
    !in_array($action, ['increase', 'decrease', 'delete'], true)) {
    backToProducts('error', 'Invalid product or action');
}
if ($action !== 'delete' && (!is_int($quantity) || $quantity <= 0 || $quantity > 10000 || strlen($notes) > 180)) {
    backToProducts('error', 'Enter a quantity from 1 to 10000 and a short reason.');
}
if ($action === 'delete' && $role !== 'owner') {
    backToProducts('error', 'Only the owner can delete products');
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
    if (!$actor || $actor['users_role'] !== $role) {
        throw new DomainException('Your account or store has changed. Sign out and sign in again.');
    }

    // Lock the balance while deciding the new amount, including simultaneous sales.
    $stmt = $connection->prepare(
        'SELECT product_quantity FROM product WHERE product_id = ? AND store_id = ? FOR UPDATE'
    );
    $stmt->bind_param('ii', $productId, $storeId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$product) {
        throw new DomainException('Product not found at your store.');
    }

    if ($action === 'delete') {
        // Deleting a sold or logged product would orphan its activity/history.
        $stmt = $connection->prepare(
            'SELECT
                (SELECT COUNT(*) FROM sales WHERE product_id = ?) AS sale_count,
                (SELECT COUNT(*) FROM stock_movement WHERE product_id = ?) AS movement_count'
        );
        $stmt->bind_param('ii', $productId, $productId);
        $stmt->execute();
        $activity = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ((int)$activity['sale_count'] > 0 || (int)$activity['movement_count'] > 0) {
            throw new DomainException('This product has sales or stock history and cannot be deleted.');
        }

        // Older products without history can still be removed.
        $stmt = $connection->prepare('DELETE FROM product WHERE product_id = ? AND store_id = ?');
        $stmt->bind_param('ii', $productId, $storeId);
        $stmt->execute();
        $stmt->close();
        $message = 'Product deleted successfully';
    } else {
        $previousQuantity = (int)$product['product_quantity'];
        if ($previousQuantity < 0) {
            throw new DomainException('This product has an invalid stock balance.');
        }
        if ($action === 'decrease' && $previousQuantity < $quantity) {
            throw new DomainException('Cannot reduce stock below zero.');
        }
        if ($action === 'increase' && $previousQuantity > 2147483647 - $quantity) {
            throw new DomainException('This quantity is too large for the stock balance.');
        }

        $newQuantity = $action === 'increase'
            ? $previousQuantity + $quantity
            : $previousQuantity - $quantity;
        $movementType = $action === 'increase' ? 'add' : 'reduce';
        $movementNotes = $notes === ''
            ? ($action === 'increase' ? 'Manual stock increase' : 'Manual stock decrease')
            : 'Manual stock ' . $action . ': ' . $notes;

        $stmt = $connection->prepare(
            'UPDATE product SET product_quantity = ? WHERE product_id = ? AND store_id = ?'
        );
        $stmt->bind_param('iii', $newQuantity, $productId, $storeId);
        $stmt->execute();
        $stmt->close();

        $stmt = $connection->prepare(
            'INSERT INTO stock_movement
             (product_id, store_id, users_id, movement_type, quantity,
              previous_quantity, new_quantity, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiisiiis', $productId, $storeId, $userId, $movementType,
            $quantity, $previousQuantity, $newQuantity, $movementNotes
        );
        $stmt->execute();
        $stmt->close();
        $message = $action === 'increase' ? 'Stock increased successfully' : 'Stock decreased successfully';
    }

    $connection->commit();
    $connection->close();
    backToProducts('message', $message);
} catch (DomainException $error) {
    $connection->rollback();
    $connection->close();
    backToProducts('error', $error->getMessage());
} catch (Throwable $error) {
    $connection->rollback();
    $connection->close();
    error_log('Product stock update failed: ' . $error->getMessage());
    backToProducts('error', 'Could not update product. Check the database migration and server logs.');
}
