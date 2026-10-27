<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../../index.html?error=Please login first');
    exit();
}

$role = $_SESSION['users_role'] ?? 'clerk';$cssPath = '../../css/style.css';$connection->close();

include '../../html/user_management.html';
?>
