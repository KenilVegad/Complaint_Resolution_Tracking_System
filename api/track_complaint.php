<?php
// Public API for tracking complaints by complaint_code
require_once("../config.php");
header('Content-Type: application/json');

$code = isset($_GET['code']) ? mysqli_real_escape_string($conn, $_GET['code']) : '';
$is_public = isset($_GET['public']) ? true : false;

if (empty($code)) {
    echo json_encode(["error" => "Complaint code required"]);
    exit;
}

// Query with joins for full complaint details
$query = "SELECT 
    c.complaint_id,
    c.complaint_code,
    c.title,
    c.description,
    c.priority,
    c.status,
    c.exact_location,
    c.submitted_at,
    c.initial_sla_deadline,
    c.resolution_sla_deadline,
    c.resolved_at,
    cc.category_name,
    w.area_name as ward_name,
    a.area_name as area_name,
    s.area_name as spot_name,
    COALESCE(staff.name, 'Not Assigned') as assigned_staff_name
FROM complaints c
LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
LEFT JOIN area_master w ON c.ward_id = w.area_id
LEFT JOIN area_master a ON c.area_id = a.area_id
LEFT JOIN area_master s ON c.spot_id = s.area_id
LEFT JOIN users staff ON c.assigned_to = staff.user_id
WHERE c.complaint_code = '$code'";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(["error" => "Database error: " . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    
    // For public access, remove sensitive staff info
    if ($is_public) {
        $data['assigned_staff_name'] = $data['assigned_to'] ? 'Assigned' : 'Pending Assignment';
    }
    
    // Add timeline data from complaint_history
    $timeline = [];
    $complaint_id = $data['complaint_id'];
    
    $timeline_query = "SELECT 
        ch.old_status,
        ch.new_status,
        ch.updated_at as entered_at,
        COALESCE(u.name, 'System') as entered_by_name,
        ch.remarks
    FROM complaint_history ch
    LEFT JOIN users u ON ch.updated_by = u.user_id
    WHERE ch.complaint_id = $complaint_id 
    ORDER BY ch.updated_at ASC";
    
    $timeline_result = mysqli_query($conn, $timeline_query);
    if ($timeline_result) {
        while ($row = mysqli_fetch_assoc($timeline_result)) {
            $timeline[] = $row;
        }
    }
    $data['timeline'] = $timeline;
    
    echo json_encode($data);
} else {
    echo json_encode(["error" => "Complaint not found"]);
}
?>
