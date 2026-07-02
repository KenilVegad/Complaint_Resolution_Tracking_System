<?php
/**
 * Public Complaint Tracking API
 * JSON endpoint for external clients to track complaints by code
 * No authentication required
 */

require_once("../config.php");
header('Content-Type: application/json');

// Get complaint code from request
$code = isset($_GET['code']) ? $_GET['code'] : '';

// Validate input
if (empty($code)) {
    http_response_code(400);
    echo json_encode(["error" => "Complaint code is required"]);
    exit;
}

// Sanitize code (uppercase and trim)
$code = strtoupper(trim($code));

// Prepared statement to prevent SQL injection
$query = "SELECT 
    c.complaint_code,
    c.title,
    c.description,
    cc.category_name,
    ward.area_name AS ward_name,
    area.area_name AS area_name,
    spot.area_name AS spot_name,
    c.exact_location,
    c.priority,
    c.status,
    c.submitted_at,
    c.initial_sla_deadline,
    c.resolution_sla_deadline,
    staff.name AS assigned_staff,
    c.is_repeated
FROM complaints c
LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
LEFT JOIN area_master ward ON c.ward_id = ward.area_id
LEFT JOIN area_master area ON c.area_id = area.area_id
LEFT JOIN area_master spot ON c.spot_id = spot.area_id
LEFT JOIN users staff ON c.assigned_to = staff.user_id
WHERE c.complaint_code = ?";

// Prepare and execute
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $code);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check if complaint found
if ($row = mysqli_fetch_assoc($result)) {
    // Clean up null values
    $response = [
        "complaint_code" => $row['complaint_code'],
        "title" => $row['title'],
        "description" => $row['description'],
        "category_name" => $row['category_name'] ?: null,
        "ward_name" => $row['ward_name'] ?: null,
        "area_name" => $row['area_name'] ?: null,
        "spot_name" => $row['spot_name'] ?: null,
        "exact_location" => $row['exact_location'] ?: null,
        "priority" => $row['priority'],
        "status" => $row['status'],
        "submitted_at" => $row['submitted_at'],
        "initial_sla_deadline" => $row['initial_sla_deadline'],
        "resolution_sla_deadline" => $row['resolution_sla_deadline'],
        "assigned_staff" => $row['assigned_staff'] ?: null,
        "is_repeated" => (bool)$row['is_repeated']
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Complaint not found"]);
}

// Clean up
mysqli_stmt_close($stmt);
?>