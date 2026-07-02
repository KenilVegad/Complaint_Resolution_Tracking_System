<?php
/**
 * AJAX API: Get Spots by Area ID
 * Used for dependent dropdown in complaint registration
 */
require_once("../config.php");

header('Content-Type: application/json');

if(!isset($_GET['area_id'])) {
    echo json_encode(['error' => 'Area ID is required']);
    exit;
}

$area_id = intval($_GET['area_id']);

// Fetch spots for the selected area
$stmt = $conn->prepare("SELECT area_id, area_name FROM area_master WHERE parent_id = ? AND area_type = 'spot' AND is_active = 1 ORDER BY area_name");
$stmt->bind_param("i", $area_id);
$stmt->execute();
$result = $stmt->get_result();

$spots = [];
while($row = $result->fetch_assoc()) {
    $spots[] = $row;
}

$stmt->close();

echo json_encode($spots);
?>
