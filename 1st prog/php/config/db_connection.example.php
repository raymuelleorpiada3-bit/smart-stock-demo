<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$database = "inventory_group_demo";

// Create connection
$connection = new mysqli($servername, $username, $password, $database);

// Check connection
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Set charset to utf8
$connection->set_charset("utf8");

// Return connection object
