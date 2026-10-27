<?php
// Compatibility endpoint for older inventory forms. Route every reduction
// through the transactional stock adjustment, which also writes Stock History.
if (isset($_POST['reduce_quantity'])) {
    $_POST['quantity'] = $_POST['reduce_quantity'];
}
$_POST['action'] = 'decrease';
require __DIR__ . '/../products/adjust_stock.php';
