<?php
session_start();
require_once("config.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$user_name = $_SESSION['user_name'];

// Auto-escalate complaints past SLA
autoEscalateComplaints();

// Role-based statistics
if($role === 'supervisor') {
    // Supervisor sees all complaints
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM complaints")->fetch_assoc()['count'],
        'pending' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('submitted', 'verified')")->fetch_assoc()['count'],
        'in_progress' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('assigned', 'in_progress')")->fetch_assoc()['count'],
        'resolved' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status IN ('resolved', 'closed')")->fetch_assoc()['count'],
        'escalated' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'escalated'")->fetch_assoc()['count']
    ];
} elseif($role === 'staff') {
    // Staff sees only assigned complaints
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id")->fetch_assoc()['count'],
        'assigned' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id AND status = 'assigned'")->fetch_assoc()['count'],
        'in_progress' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id AND status = 'in_progress'")->fetch_assoc()['count'],
        'resolved' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id AND status IN ('resolved', 'closed')")->fetch_assoc()['count'],
        'sla_alert' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id AND status NOT IN ('resolved', 'closed') AND NOW() > initial_sla_deadline")->fetch_assoc()['count']
    ];
} else {
    // Complainant sees own complaints
    $stats = [
        'total' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complainant_id = $user_id")->fetch_assoc()['count'],
        'submitted' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complainant_id = $user_id AND status = 'submitted'")->fetch_assoc()['count'],
        'in_progress' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complainant_id = $user_id AND status IN ('assigned', 'in_progress')")->fetch_assoc()['count'],
        'resolved' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complainant_id = $user_id AND status IN ('resolved', 'closed')")->fetch_assoc()['count'],
        'escalated' => $conn->query("SELECT COUNT(*) as count FROM complaints WHERE complainant_id = $user_id AND status = 'escalated'")->fetch_assoc()['count']
    ];
}

// Fetch recent complaints based on role
if($role === 'supervisor') {
    $recentQuery = "SELECT c.*, cat.category_name, u.name as complainant_name 
                   FROM complaints c 
                   LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                   LEFT JOIN users u ON c.complainant_id = u.user_id
                   ORDER BY c.submitted_at DESC LIMIT 5";
} elseif($role === 'staff') {
    $recentQuery = "SELECT c.*, cat.category_name, u.name as complainant_name 
                   FROM complaints c 
                   LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                   LEFT JOIN users u ON c.complainant_id = u.user_id
                   WHERE c.assigned_to = $user_id AND c.status NOT IN ('closed')
                   ORDER BY c.priority DESC, c.submitted_at ASC LIMIT 5";
} else {
    $recentQuery = "SELECT c.*, cat.category_name 
                   FROM complaints c 
                   LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                   WHERE c.complainant_id = $user_id 
                   ORDER BY c.submitted_at DESC LIMIT 5";
}
$recent = $conn->query($recentQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Citizen Complaint Portal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    
    <!-- Mobile Toggle -->
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar-premium').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar-premium">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-<?php echo $role === 'supervisor' ? 'shield-alt' : ($role === 'staff' ? 'hard-hat' : 'user-shield'); ?>"></i>
            </div>
            <h2 class="sidebar-title">
                <?php echo $role === 'supervisor' ? 'Admin Portal' : ($role === 'staff' ? 'Staff Portal' : 'Citizen Portal'); ?>
            </h2>
            <p class="sidebar-subtitle">
                <?php echo $role === 'supervisor' ? 'Supervisor Dashboard' : ($role === 'staff' ? 'Field Operations' : 'Digital Governance'); ?>
            </p>
            <span class="role-badge role-<?php echo $role; ?>" style="margin-top: 1rem;">
                <i class="fas fa-user"></i> <?php echo ucfirst($role); ?>
            </span>
        </div>
        
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <?php if($role === 'complainant'): ?>
                <li class="nav-item">
                    <a href="complaint.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i> Register Complaint
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_complaints.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i> My Complaints
                    </a>
                </li>
                <li class="nav-item">
                    <a href="track.php" class="nav-link">
                        <i class="fas fa-search-location"></i> Track Complaint
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if($role === 'supervisor'): ?>
                <li class="nav-item">
                    <a href="admin.php" class="nav-link">
                        <i class="fas fa-user-shield"></i> Admin Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff_performance.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="divider"></li>
                <li class="nav-item">
                    <a href="api/pending_by_area.php" target="_blank" class="nav-link" style="color: var(--accent-cyan);">
                        <i class="fas fa-code"></i> API: Pending by Area
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if($role === 'staff'): ?>
                <li class="nav-item">
                    <a href="staff.php" class="nav-link">
                        <i class="fas fa-tasks"></i> My Work
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="divider"></li>
                
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i> My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="nav-link" style="color: #ff416c;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
            <p class="page-subtitle">Here's what's happening with your complaints today</p>
        </div>
        
        <!-- Stats Grid - Role Based -->
        <div class="stats-grid">
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-value-premium"><?php echo $stats['total']; ?></div>
                <div class="stat-label-premium">Total Complaints</div>
            </div>
            
            <?php if($role === 'supervisor'): ?>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-yellow);"><i class="fas fa-hourglass-start"></i></div>
                <div class="stat-value-premium"><?php echo $stats['pending']; ?></div>
                <div class="stat-label-premium">Pending</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-spinner"></i></div>
                <div class="stat-value-premium"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label-premium">In Progress</div>
            </div>
            <div class="glass-card stat-card-premium" style="<?php echo $stats['escalated'] > 0 ? 'border: 2px solid #dc2626;' : ''; ?>">
                <div class="stat-icon-premium" style="color: <?php echo $stats['escalated'] > 0 ? '#dc2626' : 'var(--primary-400)'; ?>;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value-premium" style="color: <?php echo $stats['escalated'] > 0 ? '#dc2626' : 'inherit'; ?>"><?php echo $stats['escalated']; ?></div>
                <div class="stat-label-premium">Escalated</div>
            </div>
            
            <?php elseif($role === 'staff'): ?>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-purple);"><i class="fas fa-user-check"></i></div>
                <div class="stat-value-premium"><?php echo $stats['assigned']; ?></div>
                <div class="stat-label-premium">Newly Assigned</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-spinner"></i></div>
                <div class="stat-value-premium"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label-premium">In Progress</div>
            </div>
            <div class="glass-card stat-card-premium" style="<?php echo $stats['sla_alert'] > 0 ? 'border: 2px solid #f59e0b;' : ''; ?>">
                <div class="stat-icon-premium" style="color: <?php echo $stats['sla_alert'] > 0 ? '#f59e0b' : 'var(--primary-400)'; ?>;"><i class="fas fa-clock"></i></div>
                <div class="stat-value-premium" style="color: <?php echo $stats['sla_alert'] > 0 ? '#f59e0b' : 'inherit'; ?>"><?php echo $stats['sla_alert']; ?></div>
                <div class="stat-label-premium">SLA Alerts</div>
            </div>
            
            <?php else: ?>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-yellow);"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-value-premium"><?php echo $stats['submitted']; ?></div>
                <div class="stat-label-premium">Submitted</div>
            </div>
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-cyan);"><i class="fas fa-spinner"></i></div>
                <div class="stat-value-premium"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label-premium">In Progress</div>
            </div>
            <div class="glass-card stat-card-premium" style="<?php echo $stats['escalated'] > 0 ? 'border: 2px solid #dc2626;' : ''; ?>">
                <div class="stat-icon-premium" style="color: <?php echo $stats['escalated'] > 0 ? '#dc2626' : 'var(--primary-400)'; ?>;"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-value-premium" style="color: <?php echo $stats['escalated'] > 0 ? '#dc2626' : 'inherit'; ?>"><?php echo $stats['escalated']; ?></div>
                <div class="stat-label-premium">Escalated</div>
            </div>
            <?php endif; ?>
            
            <div class="glass-card stat-card-premium">
                <div class="stat-icon-premium" style="color: var(--accent-green);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value-premium"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label-premium">Resolved</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-family: var(--font-display); margin-bottom: 1rem; color: white;">
                <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Quick Actions
            </h3>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                <?php if($role !== 'staff'): ?>
                <a href="complaint.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Complaint
                </a>
                <?php endif; ?>
                <a href="view_complaints.php" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View All
                </a>
                <?php if($role === 'supervisor'): ?>
                <a href="admin.php" class="btn btn-secondary">
                    <i class="fas fa-user-shield"></i> Admin Panel
                </a>
                <a href="staff_performance.php" class="btn btn-success">
                    <i class="fas fa-chart-bar"></i> Staff Report
                </a>
                <?php elseif($role === 'staff'): ?>
                <a href="staff.php" class="btn btn-secondary">
                    <i class="fas fa-tasks"></i> My Work
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-family: var(--font-display); margin-bottom: 1.5rem; color: white;">
                <i class="fas fa-history" style="color: var(--accent-purple);"></i> Recent Complaints
            </h3>
            <?php if($recent->num_rows > 0): ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <?php if($role !== 'complainant'): ?><th>Complainant</th><?php endif; ?>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td style="font-family: var(--font-mono); color: var(--accent-cyan);"><?php echo htmlspecialchars($row['complaint_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <?php if($role !== 'complainant'): ?><td><?php echo htmlspecialchars($row['complainant_name'] ?? 'N/A'); ?></td><?php endif; ?>
                            <td><?php echo getStatusBadge($row['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></td>
                            <td><a href="view_complaint_detail.php?id=<?php echo $row['complaint_id']; ?>" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">View</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <h4 class="empty-state-title">No complaints yet</h4>
                <p>Start by registering your first complaint</p>
                <a href="complaint.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Register Now
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>