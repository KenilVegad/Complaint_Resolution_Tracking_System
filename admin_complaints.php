<?php
/**
 * Admin All Complaints Page
 * View and manage all complaints with full details
 */
session_start();
require_once("config.php");

// Check if supervisor
if($_SESSION['role'] !== 'supervisor') {
    header("Location: dashboard.php");
    exit();
}

$success = '';
$error = '';
$supervisorId = $_SESSION['user_id'];

// Handle complaint assignment
if(isset($_POST['assign_staff'])) {
    $complaintId = intval($_POST['complaint_id']);
    $staffId = intval($_POST['staff_id']);
    $remarks = sanitize($_POST['assignment_remarks']);
    
    if($complaintId && $staffId) {
        $stmt = $conn->prepare("UPDATE complaints SET assigned_to = ?, status = 'assigned', assigned_at = NOW() WHERE complaint_id = ?");
        $stmt->bind_param("ii", $staffId, $complaintId);
        
        if($stmt->execute()) {
            logComplaintHistory($complaintId, 'verified', 'assigned', $supervisorId, $remarks ?: 'Assigned to staff');
            
            $stmt2 = $conn->prepare("INSERT INTO assignments (complaint_id, staff_id, assigned_by, remarks) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiis", $complaintId, $staffId, $supervisorId, $remarks);
            $stmt2->execute();
            $stmt2->close();
            
            $success = "Complaint assigned successfully!";
        }
        $stmt->close();
    }
}

// Handle status change
if(isset($_POST['update_status'])) {
    $complaintId = intval($_POST['complaint_id']);
    $newStatus = sanitize($_POST['new_status']);
    $remarks = sanitize($_POST['status_remarks']);
    
    $stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if($current && isValidStatusTransition($current['status'], $newStatus)) {
        $timestampField = '';
        switch($newStatus) {
            case 'verified': $timestampField = 'verified_at'; break;
            case 'assigned': $timestampField = 'assigned_at'; break;
            case 'in_progress': $timestampField = 'in_progress_at'; break;
            case 'resolved': $timestampField = 'resolved_at'; break;
            case 'closed': $timestampField = 'closed_at'; break;
            case 'escalated': $timestampField = 'escalated_at'; break;
            case 'reopened': $timestampField = 'reopened_at'; break;
        }
        
        if($timestampField) {
            $stmt = $conn->prepare("UPDATE complaints SET status = ?, {$timestampField} = NOW() WHERE complaint_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
        }
        $stmt->bind_param("si", $newStatus, $complaintId);
        
        if($stmt->execute()) {
            logComplaintHistory($complaintId, $current['status'], $newStatus, $supervisorId, $remarks);
            $success = "Status updated successfully!";
        }
        $stmt->close();
    }
}

// Fetch all complaints
$query = "SELECT c.*, 
          cat.category_name,
          w.area_name as ward_name,
          a.area_name as area_name,
          s.area_name as spot_name,
          u.name as complainant_name,
          st.name as staff_name,
          TIMESTAMPDIFF(HOUR, c.submitted_at, NOW()) as hours_elapsed
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN area_master w ON c.ward_id = w.area_id
          LEFT JOIN area_master a ON c.area_id = a.area_id
          LEFT JOIN area_master s ON c.spot_id = s.area_id
          LEFT JOIN users u ON c.complainant_id = u.user_id
          LEFT JOIN users st ON c.assigned_to = st.user_id
          ORDER BY c.submitted_at DESC";
$complaints = $conn->query($query);

// Fetch staff list
$staffList = $conn->query("SELECT user_id, name FROM users WHERE role = 'staff' AND is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Complaints | Complaint Management System</title>
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
            <div class="sidebar-logo"><i class="fas fa-shield-alt"></i></div>
            <h2 class="sidebar-title">Admin Panel</h2>
            <p class="sidebar-subtitle">Supervisor Dashboard</p>
            <span class="role-badge role-supervisor" style="margin-top: 1rem;">
                <i class="fas fa-user-shield"></i> Supervisor
            </span>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="admin_complaints.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> All Complaints</a></li>
                <li class="nav-item"><a href="staff_performance.php" class="nav-link"><i class="fas fa-chart-bar"></i> Staff Performance</a></li>
                <li class="nav-item"><a href="staff_management.php" class="nav-link"><i class="fas fa-users-cog"></i> Staff Management</a></li>
                <li class="nav-item"><a href="heatmap.php" class="nav-link"><i class="fas fa-fire"></i> Priority Heatmap</a></li>
                <li class="nav-item"><a href="area_master.php" class="nav-link"><i class="fas fa-map"></i> Area Master</a></li>
                <li class="nav-item"><a href="category_master.php" class="nav-link"><i class="fas fa-tags"></i> Categories</a></li>
                <li class="nav-item"><a href="track.php" class="nav-link"><i class="fas fa-search-location"></i> Track Complaint</a></li>
                <li class="divider"></li>
                <li class="nav-item"><a href="api/pending_by_area.php" target="_blank" class="nav-link" style="color: var(--accent-cyan);"><i class="fas fa-code"></i> API: Pending by Area</a></li>
                <li class="divider"></li>
                <li class="nav-item"><a href="logout.php" class="nav-link" style="color: #ff416c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clipboard-list" style="color: var(--accent-cyan);"></i> All Complaints</h1>
            <p class="page-subtitle">Complete list of all registered complaints</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="glass-card" style="padding: 1.5rem;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($c = $complaints->fetch_assoc()): 
                            $slaStatus = getSLAStatus($c['resolution_sla_deadline'], $c['initial_sla_deadline'], $c['status']);
                        ?>
                        <tr>
                            <td><?php echo $c['complaint_id']; ?></td>
                            <td><code><?php echo htmlspecialchars($c['complaint_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><?php echo htmlspecialchars($c['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['ward_name']); ?></td>
                            <td>
                                <span style="text-transform: uppercase; font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; background: 
                                    <?php echo $c['status'] == 'submitted' ? 'var(--accent-yellow)' : 
                                        ($c['status'] == 'assigned' ? 'var(--accent-purple)' : 
                                        ($c['status'] == 'in_progress' ? 'var(--accent-cyan)' :
                                        ($c['status'] == 'resolved' ? 'var(--accent-green)' : 'var(--primary-400)'))); ?>;">
                                    <?php echo $c['status']; ?>
                                </span>
                            </td>
                            <td>
                                <span style="text-transform: uppercase; font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; background: 
                                    <?php echo $c['priority'] == 'critical' ? '#dc2626' : 
                                        ($c['priority'] == 'high' ? '#f97316' : 
                                        ($c['priority'] == 'medium' ? '#3b82f6' : '#10b981')); ?>;">
                                    <?php echo $c['priority']; ?>
                                </span>
                            </td>
                            <td><?php echo $c['staff_name'] ? htmlspecialchars($c['staff_name']) : '<em style="color: var(--primary-400);">Unassigned</em>'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($c['submitted_at'])); ?></td>
                            <td style="white-space: nowrap;">
                                <a href="view_complaint_detail.php?id=<?php echo $c['complaint_id']; ?>" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; margin-right: 0.25rem;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if(!$c['assigned_to'] && in_array($c['status'], ['submitted', 'verified', 'escalated'])): ?>
                                <a href="admin.php?assign=<?php echo $c['complaint_id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: linear-gradient(135deg, #8b5cf6, #ec4899);">
                                    <i class="fas fa-user-plus"></i> Assign
                                </a>
                                <?php elseif($c['assigned_to']): ?>
                                <span style="color: #10b981; font-size: 0.8rem;">
                                    <i class="fas fa-check"></i> Assigned
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>