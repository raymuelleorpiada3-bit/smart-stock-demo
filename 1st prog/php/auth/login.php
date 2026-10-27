<?php
session_start();
require_once '../config/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        header("Location: ../../index.html?error=Username and password are required");
        exit();
    }

    // Check if user exists in credential table and join with users table
    $stmt = $connection->prepare("CALL SpGetUserByUsername_Sababan(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['cre_password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['users_id'];
            $_SESSION['store_id'] = $user['store_id'];
            $_SESSION['users_role'] = $user['users_role'] ?? 'clerk';
            $_SESSION['first_name'] = $user['users_fname'];
            $_SESSION['middle_name'] = $user['users_mname'];
            $_SESSION['suffix_name'] = $user['users_sname'];
            $_SESSION['last_name'] = $user['users_lname'];
            $_SESSION['barangay_id'] = $user['barangay_id'];
            $_SESSION['barangay_name'] = $user['brgy_name'];
            $_SESSION['municipality_name'] = $user['mun_name'];
            $_SESSION['province_name'] = $user['prov_name'];
            $_SESSION['username'] = $user['cre_username'];
            $_SESSION['logged_in'] = true;

            $stmt->close();
            $connection->close();

            // Redirect to dashboard
            header("Location: ../dashboard.php");
            exit();
        } else {
            header("Location: ../../index.html?error=Invalid password");
            $stmt->close();
            $connection->close();
            exit();
        }
    } else {
        header("Location: ../../index.html?error=Username not found");
        $stmt->close();
        $connection->close();
        exit();
    }
}
