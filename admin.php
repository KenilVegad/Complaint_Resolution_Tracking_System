<?php
/**
 * Supervisor/Admin Dashboard
 * Manage all complaints, assign staff, view SLA alerts, handle escalations
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

// Auto-escalate complaints past SLA
autoEscalateComplaints();

// Handle complaint assignment
if(isset($_POST['assign_staff'])) {
    $complaintId = intval($_POST['complaint_id']);
    $staffId = intval($_POST['staff_id']);
    $remarks = sanitize($_POST['assignment_remarks']);
    
    if($complaintId && $staffId) {
        // Update complaint
        $stmt = $conn->prepare("UPDATE complaints SET assigned_to = ?, status = 'assigned', assigned_at = NOW() WHERE complaint_id = ?");
        $stmt->bind_param("ii", $staffId, $complaintId);
        
        if($stmt->execute()) {
            // Log history
            logComplaintHistory($complaintId, 'verified', 'assigned', $supervisorId, $remarks ?: 'Assigned to staff');
            
            // Add to assignments table
            $stmt2 = $conn->prepare("INSERT INTO assignments (complaint_id, staff_id, assigned_by, remarks) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("iiis", $complaintId, $staffId, $supervisorId, $remarks);
            $stmt2->execute();
            $stmt2->close();
            
            $success = "Complaint assigned successfully!";
        } else {
            $error = "Failed to assign complaint.";
        }
        $stmt->close();
    }
}

// Handle assignment from GET parameter (redirect from All Complaints)
$assignComplaint = null;
$staffList = null;
if(isset($_GET['assign'])) {
    $assignId = intval($_GET['assign']);
    
    // Fetch complaint details
    $stmt = $conn->prepare("SELECT c.*, cat.category_name, u.name as complainant_name 
                           FROM complaints c 
                           LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                           LEFT JOIN users u ON c.complainant_id = u.user_id
                           WHERE c.complaint_id = ?");
    $stmt->bind_param("i", $assignId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignComplaint = $result->fetch_assoc();
    $stmt->close();
    
    // Fetch staff list
    $staffList = $conn->query("SELECT user_id, name, email FROM users WHERE role = 'staff' AND is_active = 1 ORDER BY name");
}

// Handle status change
if(isset($_POST['update_status'])) {
    $complaintId = intval($_POST['complaint_id']);
    $newStatus = sanitize($_POST['new_status']);
    $remarks = sanitize($_POST['status_remarks']);
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();
    
    if($current && isValidStatusTransition($current['status'], $newStatus)) {
        // Update timestamp based on status
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
    } else {
        $error = "Invalid status transition.";
    }
}

// Fetch all complaints with details
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

// Dashboard statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM complaints")->fetch_assoc()['count'],
    'submitted' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'submitted'")->fetch_assoc()['count'],
    'assigned' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'assigned'")->fetch_assoc()['count'],
    'in_progress' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'in_progress'")->fetch_assoc()['count'],
    'resolved' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved'")->fetch_assoc()['count'],
    'escalated' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'escalated'")->fetch_assoc()['count'],
    'repeated' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE is_repeated = 1")->fetch_assoc()['count'],
    'sla_breach' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status NOT IN ('resolved', 'closed') AND NOW() > resolution_sla_deadline")->fetch_assoc()['count']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                <li class="nav-item"><a href="admin.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="admin_complaints.php" class="nav-link"><i class="fas fa-clipboard-list"></i> All Complaints</a></li>
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
            <h1 class="page-title">Supervisor Dashboard</h1>
            <p class="page-subtitle">Overview of all complaints and system status</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Assignment Form (shown when ?assign=ID is passed) -->
        <?php if($assignComplaint): ?>
        <div class="glass-card" style="padding: 2rem; margin-bottom: 2rem; border: 2px solid var(--accent-purple);">
            <h2 style="color: var(--accent-purple); margin-bottom: 1.5rem;">
                <i class="fas fa-user-plus"></i> Assign Complaint to Staff
            </h2>
            
            <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <h4 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($assignComplaint['title']); ?></h4>
                <p style="color: var(--primary-400); margin-bottom: 0.5rem;">
                    <strong>Code:</strong> <?php echo $assignComplaint['complaint_code']; ?> | 
                    <strong>Category:</strong> <?php echo $assignComplaint['category_name']; ?> |
                    <strong>Complainant:</strong> <?php echo htmlspecialchars($assignComplaint['complainant_name']); ?>
                </p>
                <p style="color: var(--primary-400);"><?php echo htmlspecialchars(substr($assignComplaint['description'], 0, 100)); ?>...</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="complaint_id" value="<?php echo $assignComplaint['complaint_id']; ?>">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Select Staff Member *</label>
                    <select name="staff_id" class="form-control" required>
                        <option value="">-- Choose Staff --</option>
                        <?php if($staffList && $staffList->num_rows > 0): ?>
                            <?php while($staff = $staffList->fetch_assoc()): ?>
                            <option value="<?php echo $staff['user_id']; ?>">
                                <?php echo htmlspecialchars($staff['name']); ?><?php echo !empty($staff['email']) ? ' (' . htmlspecialchars($staff['email']) . ')' : ''; ?>
                            </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No active staff found</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Assignment Remarks (Optional)</label>
                    <textarea name="assignment_remarks" class="form-control" rows="3" placeholder="Add any special instructions for the staff..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="assign_staff" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-user-check"></i> Assign Complaint
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-value-premium"><?php echo $stats['total']; ?></div>
                <div class="stat-label-premium">Total</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-yellow);"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-value-premium"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label-premium">Submitted</div>
            </div>
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
            <div class="glass-card stat-card-premium" style="border: 2px solid #dc2626;">
                <div class="stat-icon-premium" style="color: #dc2626;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value-premium" style="color: #dc2626;"><?php echo $stats['escalated']; ?></div>
                <div class="stat-label-premium">Escalated</div>
            </div>
            <div class="glass-card stat-card-premium" style="border: 2px solid #f59e0b;">
                <div class="stat-icon-premium" style="color: #f59e0b;"><i class="fas fa-clock"></i></div>
                <div class="stat-value-premium" style="color: #f59e0b;"><?php echo $stats['sla_breach']; ?></div>
                <div class="stat-label-premium">SLA Breach</div>
            </div>
            <div class="glass-card stat-card-premium" style="border: 2px solid #8b5cf6;">
                <div class="stat-icon-premium" style="color: #8b5cf6;"><i class="fas fa-clone"></i></div>
                <div class="stat-value-premium" style="color: #8b5cf6;"><?php echo $stats['repeated']; ?></div>
                <div class="stat-label-premium">Repeated</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-family: var(--font-display); margin-bottom: 1rem; color: white;">
                <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Quick Actions
            </h3>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                <a href="admin_complaints.php?filter=escalated" class="btn btn-danger">
                    <i class="fas fa-exclamation-triangle"></i> View Escalated (<?php echo $stats['escalated']; ?>)
                </a>
                <a href="admin_complaints.php?filter=sla_breach" class="btn btn-secondary" style="border-color: #f59e0b; color: #f59e0b;">
                    <i class="fas fa-clock"></i> SLA Breaches (<?php echo $stats['sla_breach']; ?>)
                </a>
                <a href="admin_complaints.php?filter=repeated" class="btn btn-secondary" style="border-color: #8b5cf6; color: #8b5cf6;">
                    <i class="fas fa-clone"></i> Repeated Flags (<?php echo $stats['repeated']; ?>)
                </a>
                <a href="staff_performance.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Staff Report
                </a>
                <a href="heatmap.php" class="btn btn-success">
                    <i class="fas fa-fire"></i> Heatmap View
                </a>
            </div>
        </div>
        
        <!-- Recent Complaints Table -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-family: var(--font-display); margin-bottom: 1.5rem; color: white;">
                <i class="fas fa-list" style="color: var(--accent-cyan);"></i> Recent Complaints
            </h3>
            
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>SLA Status</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while($row = $complaints->fetch_assoc()): 
                            if($count++ >= 10) break; // Show only 10 recent
                            
                            $slaStatus = getSLAStatus($row['resolution_sla_deadline'], $row['initial_sla_deadline'], $row['status']);
                            $slaClass = $slaStatus === 'escalated' ? 'color: #dc2626; font-weight: bold;' : 
                                       ($slaStatus === 'initial_breach' ? 'color: #f59e0b;' : 'color: #10b981;');
                            $slaText = $slaStatus === 'escalated' ? 'ESCALATED' : 
                                      ($slaStatus === 'initial_breach' ? 'Late' : 'On Track');
                        ?>
                        <tr>
                            <td style="font-family: var(--font-mono);">
                                <?php echo htmlspecialchars($row['complaint_code']); ?>
                                <?php if($row['is_repeated']): ?>
                                <span style="background: #8b5cf6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.7rem;">REPEAT</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['ward_name']); ?></td>
                            <td><?php echo getStatusBadge($row['status']); ?></td>
                            <td style="<?php echo $slaClass; ?>"><?php echo $slaText; ?></td>
                            <td><?php echo $row['staff_name'] ?: '<em style="color: var(--primary-400);">Unassigned</em>'; ?></td>
                            <td>
                                <a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 1rem; text-align: center;">
                <a href="admin_complaints.php" class="btn btn-primary">View All Complaints</a>
            </div>
        </div>
    </main>
</body>
</html>
