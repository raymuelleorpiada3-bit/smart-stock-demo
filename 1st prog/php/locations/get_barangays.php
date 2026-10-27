<?php
require_once '../config/db_connection.php';

header('Content-Type: application/json');

$municipality_id = isset($_GET['municipality_id']) ? intval($_GET['municipality_id']) : 0;

$barangays = array();

if ($municipality_id > 0) {
    $query = "CALL `SpSelectBrgyByMuni_Fabia`(?)";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $municipality_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $barangays[] = $row;
        }
    }
    $stmt->close();
}

$connection->close();
echo json_encode($barangays);
