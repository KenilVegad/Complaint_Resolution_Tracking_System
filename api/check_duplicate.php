<?php
/**
 * AJAX API: Check for duplicate/repeated complaints
 * Special Rule (U is Odd): Flag repeated complaints within 7 days
 */
require_once("../config.php");

header('Content-Type: application/json');

if(!isset($_GET['ward_id']) || !isset($_GET['area_id']) || !isset($_GET['spot_id']) || !isset($_GET['category_id'])) {
    echo json_encode(['error' => 'All location parameters are required']);
    exit;
}

$ward_id = intval($_GET['ward_id']);
$area_id = intval($_GET['area_id']);
$spot_id = intval($_GET['spot_id']);
$category_id = intval($_GET['category_id']);

// Check for existing complaint in same location/category within 7 days
$stmt = $conn->prepare("SELECT complaint_id, complaint_code, title, submitted_at 
                       FROM complaints 
                       WHERE ward_id = ? AND area_id = ? AND spot_id = ? AND category_id = ?
                       AND status NOT IN ('resolved', 'closed')
                       AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                       ORDER BY submitted_at ASC LIMIT 1");
$stmt->bind_param("iiii", $ward_id, $area_id, $spot_id, $category_id);
$stmt->execute();
$result = $stmt->get_result();

if($existing = $result->fetch_assoc()) {
    echo json_encode([
        'is_repeated' => true,
        'existing_complaint' => $existing,
        'message' => 'A similar complaint (ID: ' . $existing['complaint_code'] . ') was submitted on ' . date('M d, Y', strtotime($existing['submitted_at'])) . '. This will be flagged as a repeated complaint.'
    ]);
} else {
    echo json_encode(['is_repeated' => false]);
}

$stmt->close();
?>
