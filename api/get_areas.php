<?php
/**
 * AJAX API: Get Areas by Ward ID
 * Used for dependent dropdown in complaint registration
 */
require_once("../config.php");

header('Content-Type: application/json');

if(!isset($_GET['ward_id'])) {
    echo json_encode(['error' => 'Ward ID is required']);
    exit;
}

$ward_id = intval($_GET['ward_id']);

// Fetch areas for the selected ward
$stmt = $conn->prepare("SELECT area_id, area_name FROM area_master WHERE parent_id = ? AND area_type = 'area' AND is_active = 1 ORDER BY area_name");
$stmt->bind_param("i", $ward_id);
$stmt->execute();
$result = $stmt->get_result();

$areas = [];
while($row = $result->fetch_assoc()) {
    $areas[] = $row;
}

$stmt->close();

echo json_encode($areas);
?>
