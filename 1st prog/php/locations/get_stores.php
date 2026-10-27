<?php
require_once '../config/db_connection.php';

header('Content-Type: application/json');

$query = "CALL `SpSelectStores_Fabia`()";
$stmt = $connection->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$stores = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
}

$connection->close();
echo json_encode($stores);
