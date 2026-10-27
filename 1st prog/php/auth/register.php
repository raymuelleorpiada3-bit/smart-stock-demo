<?php
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $suffix_name = trim($_POST['suffix_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $barangay_id = !empty($_POST['barangay_id']) ? intval($_POST['barangay_id']) : 0;
    $store_option = $_POST['store_option'];
    $store_id = !empty($_POST['store_id']) ? $_POST['store_id'] : null;
    $custom_store_name = !empty($_POST['custom_store_name']) ? trim($_POST['custom_store_name']) : null;

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($username) || empty($password)) {
        header("Location: ../../index.html?error=First name, last name, username and password are required&form=register");
        exit();
    }

    if ($barangay_id <= 0) {
        header("Location: ../../index.html?error=Please select your location (Province, Municipality, and Barangay)&form=register");
        exit();
    }

    if ($store_option === 'existing' && empty($store_id)) {
        header("Location: ../../index.html?error=Please select a store&form=register");
        exit();
    }

    if ($store_option === 'new' && empty($custom_store_name)) {
        header("Location: ../../index.html?error=Please enter a store name&form=register");
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: ../../index.html?error=Passwords do not match&form=register");
        exit();
    }

    if (strlen($password) < 6) {
        header("Location: ../../index.html?error=Password must be at least 6 characters&form=register");
        exit();
    }

    // Check if username already exists in credential table
    $checkUser = $connection->prepare("CALL SpCheckUsernameExists_Sababan(?, @username_exists)");
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    
    $resultSet = $connection->query("SELECT @username_exists AS exists_flag");
    $resultRow = $resultSet->fetch_assoc();
    $checkUser->close();

    if ($resultRow['exists_flag']) {
        header("Location: ../../index.html?error=Username already registered&form=register");
        exit();
    }

    // Start transaction for data integrity
    $connection->begin_transaction();

    try {
        // If creating new store, insert it first
        if ($store_option === 'new') {
            $stmt_store = $connection->prepare("CALL SpCreateStore_Sababan(?, @store_id)");
            $stmt_store->bind_param("s", $custom_store_name);

            if (!$stmt_store->execute()) {
                throw new Exception("Failed to create store");
            }

            $resultSet = $connection->query("SELECT @store_id AS store_id");
            $resultRow = $resultSet->fetch_assoc();
            $store_id = $resultRow['store_id'];
            $stmt_store->close();
        }

        // Insert new user into users table
        $stmt1 = $connection->prepare("CALL SpCreateUser_Herminado(?, ?, ?, ?, ?, ?, @user_id)");
        $stmt1->bind_param("iissss", $store_id, $barangay_id, $first_name, $middle_name, $suffix_name, $last_name);

        if (!$stmt1->execute()) {
            throw new Exception("Failed to create user record");
        }

        $resultSet = $connection->query("SELECT @user_id AS user_id");
        $resultRow = $resultSet->fetch_assoc();
        $users_id = $resultRow['user_id'];
        $stmt1->close();

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert credentials into credential table
        $stmt2 = $connection->prepare("CALL SpCreateCredential_Herminado(?, ?, ?, @cred_success)");
        $stmt2->bind_param("iss", $users_id, $username, $hashed_password);

        if (!$stmt2->execute()) {
            throw new Exception("Failed to create credentials");
        }

        $stmt2->close();

        // Commit transaction
        $connection->commit();
        header("Location: ../../index.html?message=Registration successful! Please login.&form=login");
        $connection->close();
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $connection->rollback();
        header("Location: ../../index.html?error=Registration failed: " . $e->getMessage() . "&form=register");
        $connection->close();
        exit();
    }
}
