<?php
/**
 * Public API: Pending Complaints by Area
 * JSON endpoint showing pending complaints grouped by ward and area
 * No authentication required
 */

require_once("../config.php");
header('Content-Type: application/json');

// Get optional ward_id filter
$ward_id = isset($_GET['ward_id']) ? intval($_GET['ward_id']) : null;
$debug = isset($_GET['debug']) ? true : false;

// First, check total pending complaints (without joins)
$check_query = "SELECT COUNT(*) as total FROM complaints WHERE status NOT IN ('resolved', 'closed')";
$check_result = mysqli_query($conn, $check_query);
$total_pending_raw = mysqli_fetch_assoc($check_result)['total'];

// Main query to get all pending complaints with their ward and area info
$query = "SELECT 
    c.complaint_id,
    c.title,
    c.status,
    c.priority,
    c.ward_id,
    c.area_id,
    w.area_name AS ward_name,
    a.area_name AS area_name
FROM complaints c
LEFT JOIN area_master w ON c.ward_id = w.area_id
LEFT JOIN area_master a ON c.area_id = a.area_id
WHERE c.status NOT IN ('resolved', 'closed')";

// Add ward filter if provided
$params = [];
$types = "";

if ($ward_id) {
    $query .= " AND c.ward_id = ?";
    $params[] = $ward_id;
    $types .= "i";
}

$query .= " ORDER BY w.area_name, a.area_name";

// Prepare and execute
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Database error: " . mysqli_error($conn)]);
    exit;
}

// Bind parameters if any
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Process data into hierarchical structure
$data = [];
$total_pending = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $ward_id_key = $row['ward_id'];
    $area_id_key = $row['area_id'];
    
    // Initialize ward if not exists
    if (!isset($data[$ward_id_key])) {
        $data[$ward_id_key] = [
            'ward_id' => intval($row['ward_id']),
            'ward_name' => $row['ward_name'],
            'total_pending' => 0,
            'areas' => []
        ];
    }
    
    // Initialize area if not exists within ward
    if (!isset($data[$ward_id_key]['areas'][$area_id_key])) {
        $data[$ward_id_key]['areas'][$area_id_key] = [
            'area_id' => intval($row['area_id']),
            'area_name' => $row['area_name'],
            'pending_count' => 0,
            'critical_count' => 0,
            'high_count' => 0
        ];
    }
    
    // Increment counts
    $data[$ward_id_key]['total_pending']++;
    $data[$ward_id_key]['areas'][$area_id_key]['pending_count']++;
    $total_pending++;
    
    // Count by priority
    if ($row['priority'] === 'critical') {
        $data[$ward_id_key]['areas'][$area_id_key]['critical_count']++;
    } elseif ($row['priority'] === 'high') {
        $data[$ward_id_key]['areas'][$area_id_key]['high_count']++;
    }
}

// Convert associative arrays to indexed arrays for JSON
$output_data = [];
foreach ($data as $ward) {
    $ward['areas'] = array_values($ward['areas']);
    $output_data[] = $ward;
}

// Build final response
$response = [
    "generated_at" => date('Y-m-d H:i:s'),
    "total_pending" => $total_pending,
    "filter" => $ward_id ? "Ward $ward_id" : "All Wards",
    "debug" => [
        "total_pending_in_db" => intval($total_pending_raw),
        "note" => "If total_pending_in_db > 0 but data is empty, check area_master table has matching area_ids"
    ],
    "data" => $output_data
];

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);

// Clean up
mysqli_stmt_close($stmt);
?>
