<?php
require_once '../config/db_connection.php';

header('Content-Type: application/json');

$query = "CALL `SpSelectProv_Maramag`()";
$stmt = $connection->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$provinces = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $provinces[] = $row;
    }
}
$stmt->close();

$connection->close();
echo json_encode($provinces);
