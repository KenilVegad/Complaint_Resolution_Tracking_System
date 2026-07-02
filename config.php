<?php
/**
 * Configuration File
 * Area-Based Complaint & Resolution Tracking System
 * 
 * Personalization Config (Enrollment: 230210107075)
 * B = 0 (Regular student)
 * S = 75 (Last 3 digits)
 * U = 75 + (80 × 0) = 75
 * D = ((75-1) mod 8) + 1 = 3 → Road/Pathway Surface Damage
 * A = ((75-1) mod 4) + 1 = 3 → Ward → Area → Spot
 * Initial Response SLA = 5 + ((75-1) mod 4) = 7 hours
 * Resolution SLA = 24 + (((75-1) mod 6) × 6) = 36 hours
 * Special Rule: U = 75 (Odd) → Repeated Complaint Flagging
 * Mandatory Report R = ((75-1) mod 6) + 1 = 3 → Staff Performance Summary
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'complaint_db');

// SLA Configuration (in hours)
define('INITIAL_SLA_HOURS', 7);
define('RESOLUTION_SLA_HOURS', 36);

// Special Rule: U is Odd → Repeated Complaint Flagging
define('SPECIAL_RULE_REPEAT_FLAG', true);

// Complaint Domain
define('DOMAIN_NAME', 'Road / Pathway Surface Damage');
define('DOMAIN_CODE', 'ROAD');

// Area Hierarchy
define('AREA_MODEL', 'Ward → Area → Spot');
define('AREA_LEVEL_1', 'Ward');
define('AREA_LEVEL_2', 'Area');
define('AREA_LEVEL_3', 'Spot');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// Session & Cookie Configuration (only set if session not already started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection with Error Handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error. Please try again later.");
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate unique complaint code
 * Format: ROAD-YYYY-XXXX
 * @return string
 */
function generateComplaintCode() {
    $year = date('Y');
    $prefix = DOMAIN_CODE . '-' . $year . '-';
    
    global $conn;
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(complaint_code, LENGTH(?) + 1) AS UNSIGNED)) as max_num 
                            FROM complaints WHERE complaint_code LIKE ?");
    $likePattern = $prefix . '%';
    $stmt->bind_param("ss", $prefix, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $maxNum = $row['max_num'] ?? 0;
    $nextNum = $maxNum + 1;
    $stmt->close();
    
    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate SLA deadlines
 * @param string $submittedAt
 * @return array [initial_sla, resolution_sla]
 */
function calculateSLADeadlines($submittedAt) {
    $submitted = new DateTime($submittedAt);
    
    $initialSLA = clone $submitted;
    $initialSLA->modify('+' . INITIAL_SLA_HOURS . ' hours');
    
    $resolutionSLA = clone $submitted;
    $resolutionSLA->modify('+' . RESOLUTION_SLA_HOURS . ' hours');
    
    return [
        'initial_sla' => $initialSLA->format('Y-m-d H:i:s'),
        'resolution_sla' => $resolutionSLA->format('Y-m-d H:i:s')
    ];
}

/**
 * Check SLA status
 * @param string $resolutionSLA
 * @param string $initialSLA
 * @param string $currentStatus
 * @return string
 */
function getSLAStatus($resolutionSLA, $initialSLA, $currentStatus) {
    if (in_array($currentStatus, ['resolved', 'closed'])) {
        return 'completed';
    }
    
    $now = new DateTime();
    $resSLA = new DateTime($resolutionSLA);
    $initSLA = new DateTime($initialSLA);
    
    if ($now > $resSLA) {
        return 'escalated';
    } elseif ($now > $initSLA) {
        return 'initial_breach';
    }
    
    return 'on_track';
}

/**
 * Get status badge HTML
 * @param string $status
 * @return string
 */
function getStatusBadge($status) {
    $statusClasses = [
        'submitted' => 'status-submitted',
        'verified' => 'status-verified',
        'assigned' => 'status-assigned',
        'in_progress' => 'status-progress',
        'resolved' => 'status-resolved',
        'closed' => 'status-closed',
        'reopened' => 'status-reopened',
        'escalated' => 'status-escalated'
    ];
    
    $class = $statusClasses[$status] ?? 'status-submitted';
    $displayStatus = ucwords(str_replace('_', ' ', $status));
    
    return "<span class='status-badge {$class}'>{$displayStatus}</span>";
}

/**
 * Validate file upload
 * @param array $file
 * @return array [success, message, path]
 */
function validateFileUpload($file) {
    // Check upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        $message = $errorMessages[$file['error']] ?? 'File upload failed (Error: ' . $file['error'] . ')';
        return ['success' => false, 'message' => $message, 'path' => null];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit', 'path' => null];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, PDF allowed', 'path' => null];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Invalid file extension', 'path' => null];
    }
    
    // Create uploads directory if it doesn't exist
    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create uploads directory', 'path' => null];
        }
    }
    
    // Check if directory is writable
    if (!is_writable(UPLOAD_DIR)) {
        return ['success' => false, 'message' => 'Upload directory is not writable', 'path' => null];
    }
    
    // Generate secure filename
    $newFilename = DOMAIN_CODE . '_' . uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = UPLOAD_DIR . $newFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'message' => 'Failed to save file to uploads folder', 'path' => null];
    }
    
    return [
        'success' => true, 
        'message' => 'File uploaded successfully', 
        'path' => 'uploads/' . $newFilename,
        'original_name' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $mimeType
    ];
}

/**
 * Log complaint history
 * @param int $complaintId
 * @param string $oldStatus
 * @param string $newStatus
 * @param int $updatedBy
 * @param string $remarks
 */
function logComplaintHistory($complaintId, $oldStatus, $newStatus, $updatedBy, $remarks = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO complaint_history (complaint_id, old_status, new_status, updated_by, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $complaintId, $oldStatus, $newStatus, $updatedBy, $remarks);
    $stmt->execute();
    $stmt->close();
}

/**
 * Check for repeated complaint (Special Rule for Odd U)
 * @param int $wardId
 * @param int $areaId
 * @param int $spotId
 * @param int $categoryId
 * @return array|null
 */
function checkRepeatedComplaint($wardId, $areaId, $spotId, $categoryId) {
    if (!SPECIAL_RULE_REPEAT_FLAG) {
        return null;
    }
    
    global $conn;
    
    $stmt = $conn->prepare("SELECT complaint_id, complaint_code, title, submitted_at 
                           FROM complaints 
                           WHERE ward_id = ? AND area_id = ? AND spot_id = ? AND category_id = ?
                           AND status NOT IN ('resolved', 'closed')
                           AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY submitted_at ASC LIMIT 1");
    $stmt->bind_param("iiii", $wardId, $areaId, $spotId, $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    return $existing;
}

/**
 * Get valid status transitions
 * @param string $currentStatus
 * @return array
 */
function getValidStatusTransitions($currentStatus) {
    $transitions = [
        'submitted' => ['verified', 'escalated'],
        'verified' => ['assigned', 'escalated'],
        'assigned' => ['in_progress', 'escalated'],
        'in_progress' => ['resolved', 'escalated'],
        'resolved' => ['closed', 'reopened'],
        'closed' => ['reopened'],
        'reopened' => ['verified', 'escalated'],
        'escalated' => ['assigned', 'in_progress', 'resolved']
    ];
    
    return $transitions[$currentStatus] ?? [];
}

/**
 * Check if status transition is valid
 * @param string $fromStatus
 * @param string $toStatus
 * @return bool
 */
function isValidStatusTransition($fromStatus, $toStatus) {
    if ($fromStatus === $toStatus) return true;
    $validTransitions = getValidStatusTransitions($fromStatus);
    return in_array($toStatus, $validTransitions);
}

/**
 * Set cookie for saved filter
 * @param string $name
 * @param string $value
 */
function setFilterCookie($name, $value) {
    setcookie('filter_' . $name, $value, time() + (7 * 24 * 60 * 60), '/'); // 7 days
}

/**
 * Get cookie for saved filter
 * @param string $name
 * @return string|null
 */
function getFilterCookie($name) {
    return $_COOKIE['filter_' . $name] ?? null;
}

/**
 * Auto-escalate complaints past SLA
 */
function autoEscalateComplaints() {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE complaints 
                          SET status = 'escalated', escalated_at = NOW() 
                          WHERE status NOT IN ('resolved', 'closed', 'escalated')
                          AND NOW() > resolution_sla_deadline");
    $stmt->execute();
    $stmt->close();
}
?>

