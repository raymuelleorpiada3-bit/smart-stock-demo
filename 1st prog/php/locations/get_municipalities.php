<?php
require_once '../config/db_connection.php';

header('Content-Type: application/json');

$province_id = isset($_GET['province_id']) ? intval($_GET['province_id']) : 0;

$municipalities = array();

if ($province_id > 0) {
    $query = "CALL `SpSelectMuniByPro_Lago`(?)";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $province_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $municipalities[] = $row;
        }
    }
    $stmt->close();
}

$connection->close();
echo json_encode($municipalities);
