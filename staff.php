<?php
/**
 * Staff Dashboard - View and manage assigned complaints
 */
session_start();
require_once("config.php");

if($_SESSION['role'] !== 'staff') {
    header("Location: dashboard.php");
    exit();
}

$staffId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle status update
if(isset($_POST['update_status'])) {
    $complaintId = intval($_POST['complaint_id']);
    $newStatus = sanitize($_POST['new_status']);
    $remarks = sanitize($_POST['remarks']);
    
    // Verify complaint is assigned to this staff
    $stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ? AND assigned_to = ?");
    $stmt->bind_param("ii", $complaintId, $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();
    
    if($current && isValidStatusTransition($current['status'], $newStatus)) {
        // Update timestamp
        $timestampField = '';
        switch($newStatus) {
            case 'in_progress': $timestampField = 'in_progress_at'; break;
            case 'resolved': $timestampField = 'resolved_at'; break;
        }
        
        if($timestampField) {
            $stmt = $conn->prepare("UPDATE complaints SET status = ?, {$timestampField} = NOW() WHERE complaint_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
        }
        $stmt->bind_param("si", $newStatus, $complaintId);
        
        if($stmt->execute()) {
            logComplaintHistory($complaintId, $current['status'], $newStatus, $staffId, $remarks);
            
            // Handle action proof upload for resolved status
            if($newStatus === 'resolved' && isset($_FILES['action_proof']) && $_FILES['action_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = validateFileUpload($_FILES['action_proof']);
                if($uploadResult['success']) {
                    $stmt2 = $conn->prepare("INSERT INTO complaint_attachments 
                        (complaint_id, file_path, file_type, original_name, file_size, mime_type, uploaded_by) 
                        VALUES (?, ?, 'action_proof', ?, ?, ?, ?)");
                    $stmt2->bind_param("issisi", $complaintId, $uploadResult['path'], 
                        $uploadResult['original_name'], $uploadResult['file_size'], 
                        $uploadResult['mime_type'], $staffId);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            
            $success = "Status updated successfully!";
        }
        $stmt->close();
    } else {
        $error = "Invalid status transition or complaint not assigned to you.";
    }
}

// Fetch assigned complaints
$query = "SELECT c.*, 
          cat.category_name,
          w.area_name as ward_name,
          a.area_name as area_name,
          s.area_name as spot_name,
          u.name as complainant_name,
          TIMESTAMPDIFF(HOUR, c.submitted_at, NOW()) as hours_elapsed,
          TIMESTAMPDIFF(HOUR, NOW(), c.resolution_sla_deadline) as hours_to_sla
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN area_master w ON c.ward_id = w.area_id
          LEFT JOIN area_master a ON c.area_id = a.area_id
          LEFT JOIN area_master s ON c.spot_id = s.area_id
          LEFT JOIN users u ON c.complainant_id = u.user_id
          WHERE c.assigned_to = ? AND c.status NOT IN ('closed')
          ORDER BY c.priority DESC, c.submitted_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$complaints = $stmt->get_result();
$stmt->close();

// Statistics
$stats = [
    'assigned' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $staffId AND status = 'assigned'")->fetch_assoc()['count'],
    'in_progress' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $staffId AND status = 'in_progress'")->fetch_assoc()['count'],
    'resolved' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $staffId AND status = 'resolved'")->fetch_assoc()['count'],
    'sla_alert' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $staffId AND status NOT IN ('resolved', 'closed') AND NOW() > initial_sla_deadline")->fetch_assoc()['count']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="animated-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar-premium').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <aside class="sidebar-premium">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fas fa-hard-hat"></i></div>
            <h2 class="sidebar-title">Staff Portal</h2>
            <p class="sidebar-subtitle">Field Operations</p>
            <span class="role-badge role-staff" style="margin-top: 1rem;">
                <i class="fas fa-user"></i> Staff
            </span>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item"><a href="staff.php" class="nav-link active"><i class="fas fa-tasks"></i> My Work</a></li>
                <li class="nav-item"><a href="staff_history.php" class="nav-link"><i class="fas fa-history"></i> History</a></li>
                <li class="nav-item"><a href="track.php" class="nav-link"><i class="fas fa-search-location"></i> Track Complaint</a></li>
                <li class="divider"></li>
                <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link" style="color: #ff416c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p class="page-subtitle">Your assigned complaints and work queue</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 2rem;">
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-purple);"><i class="fas fa-user-check"></i></div>
                <div class="stat-value-premium"><?php echo $stats['assigned']; ?></div>
                <div class="stat-label-premium">Assigned</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-spinner"></i></div>
                <div class="stat-value-premium"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label-premium">In Progress</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-green);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value-premium"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label-premium">Resolved</div>
            </div>
            <div class="glass-card stat-card-premium" style="<?php echo $stats['sla_alert'] > 0 ? 'border: 2px solid #f59e0b;' : ''; ?>">
                <div class="stat-icon-premium" style="color: <?php echo $stats['sla_alert'] > 0 ? '#f59e0b' : 'var(--primary-400)'; ?>;"><i class="fas fa-clock"></i></div>
                <div class="stat-value-premium" style="color: <?php echo $stats['sla_alert'] > 0 ? '#f59e0b' : 'inherit'; ?>"><?php echo $stats['sla_alert']; ?></div>
                <div class="stat-label-premium">SLA Alerts</div>
            </div>
        </div>
        
        <!-- Assigned Complaints -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-clipboard-list" style="color: var(--accent-cyan);"></i> Assigned Complaints
            </h3>
            
            <?php if($complaints->num_rows > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php while($c = $complaints->fetch_assoc()): 
                    $slaStatus = getSLAStatus($c['resolution_sla_deadline'], $c['initial_sla_deadline'], $c['status']);
                    $slaClass = $slaStatus === 'escalated' ? 'border: 2px solid #dc2626;' : 
                               ($slaStatus === 'initial_breach' ? 'border: 2px solid #f59e0b;' : '');
                ?>
                <div class="glass-card" style="padding: 1.5rem; <?php echo $slaClass; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                                <span style="font-family: var(--font-mono); color: var(--accent-cyan);"><?php echo htmlspecialchars($c['complaint_code']); ?></span>
                                <?php echo getStatusBadge($c['status']); ?>
                                <?php if($c['priority'] === 'critical' || $c['priority'] === 'high'): ?>
                                <span style="background: <?php echo $c['priority'] === 'critical' ? '#dc2626' : '#f59e0b'; ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                                    <?php echo strtoupper($c['priority']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if($c['is_repeated']): ?>
                                <span style="background: #8b5cf6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">REPEATED</span>
                                <?php endif; ?>
                            </div>
                            <h4 style="color: white; margin: 0.5rem 0;"><?php echo htmlspecialchars($c['title']); ?></h4>
                            <p style="color: var(--primary-400); margin: 0; font-size: 0.9rem;">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($c['ward_name'] . ' → ' . $c['area_name'] . ' → ' . $c['spot_name']); ?>
                            </p>
                            <p style="color: var(--primary-300); margin: 0.5rem 0; font-size: 0.85rem;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($c['complainant_name']); ?>
                                <span style="margin: 0 0.5rem;">|</span>
                                <i class="fas fa-clock"></i> 
                                <?php if($c['hours_to_sla'] > 0): ?>
                                    <span style="color: <?php echo $c['hours_to_sla'] < 8 ? '#f59e0b' : '#10b981'; ?>">
                                        <?php echo $c['hours_to_sla']; ?> hrs to SLA
                                    </span>
                                <?php else: ?>
                                    <span style="color: #dc2626;">SLA BREACHED</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div style="text-align: right;">
                            <a href="view_complaint_detail.php?id=<?php echo $c['complaint_id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if($c['status'] !== 'resolved' && $c['status'] !== 'closed'): ?>
                            <button onclick="toggleUpdateForm(<?php echo $c['complaint_id']; ?>)" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                <i class="fas fa-edit"></i> Update Status
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Status Update Form (Hidden by default) -->
                    <div id="updateForm<?php echo $c['complaint_id']; ?>" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="complaint_id" value="<?php echo $c['complaint_id']; ?>">
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">New Status</label>
                                    <select name="new_status" class="form-control" required>
                                        <?php 
                                        $validTransitions = getValidStatusTransitions($c['status']);
                                        foreach($validTransitions as $status): 
                                            if(in_array($status, ['in_progress', 'resolved', 'escalated'])):
                                        ?>
                                        <option value="<?php echo $status; ?>"><?php echo ucwords(str_replace('_', ' ', $status)); ?></option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                
                                <?php if(in_array('resolved', $validTransitions)): ?>
                                <div class="form-group">
                                    <label class="form-label">Action Proof (Photo)</label>
                                    <input type="file" name="action_proof" class="form-control" accept="image/*">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="Enter your remarks about this status update..." required></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update
                                </button>
                                <button type="button" onclick="toggleUpdateForm(<?php echo $c['complaint_id']; ?>)" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                <h4 class="empty-state-title">No assigned complaints</h4>
                <p>You don't have any active complaints assigned to you.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
    function toggleUpdateForm(id) {
        const form = document.getElementById('updateForm' + id);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</body>
</html>
