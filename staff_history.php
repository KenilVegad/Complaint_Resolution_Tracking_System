<?php
/**
 * Staff History - View resolved/closed complaints assigned to staff
 */
session_start();
require_once("config.php");

if($_SESSION['role'] !== 'staff') {
    header("Location: dashboard.php");
    exit();
}

$staffId = $_SESSION['user_id'];

// Fetch staff's resolved/closed complaints history
$query = "SELECT c.*, 
          cat.category_name,
          w.area_name as ward_name,
          a.area_name as area_name,
          s.area_name as spot_name,
          u.name as complainant_name
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN area_master w ON c.ward_id = w.area_id
          LEFT JOIN area_master a ON c.area_id = a.area_id
          LEFT JOIN area_master s ON c.spot_id = s.area_id
          LEFT JOIN users u ON c.complainant_id = u.user_id
          WHERE c.assigned_to = ? AND c.status IN ('resolved', 'closed')
          ORDER BY c.resolved_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$complaints = $stmt->get_result();
$count = $complaints->num_rows;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work History | Staff Portal</title>
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
                <li class="nav-item"><a href="staff.php" class="nav-link"><i class="fas fa-tasks"></i> My Work</a></li>
                <li class="nav-item"><a href="staff_history.php" class="nav-link active"><i class="fas fa-history"></i> History</a></li>
                <li class="nav-item"><a href="track.php" class="nav-link"><i class="fas fa-search-location"></i> Track Complaint</a></li>
                <li class="divider"></li>
                <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link" style="color: #ff416c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-history" style="color: var(--accent-cyan);"></i> Work History</h1>
            <p class="page-subtitle">Resolved and closed complaints you've handled</p>
        </div>
        
        <!-- Results Count -->
        <div style="margin-bottom: 1.5rem; color: var(--primary-300);">
            <i class="fas fa-list"></i> Showing <strong style="color: var(--accent-cyan);"><?php echo $count; ?></strong> completed complaint<?php echo $count != 1 ? 's' : ''; ?>
        </div>
        
        <!-- Complaints Grid -->
        <?php if($count > 0): ?>
        <div class="cards-grid">
            <?php while($c = $complaints->fetch_assoc()): 
                $status_class = strtolower(str_replace(' ', '-', $c['status']));
            ?>
            <div class="glass-card complaint-card fade-in">
                <div class="complaint-header">
                    <div>
                        <span class="complaint-id"><?php echo $c['complaint_code']; ?></span>
                        <h3 class="complaint-title" style="margin-top: 0.5rem;"><?php echo htmlspecialchars($c['title']); ?></h3>
                    </div>
                    <span class="status-badge status-<?php echo $status_class; ?>">
                        <?php echo $c['status']; ?>
                    </span>
                </div>
                
                <p class="complaint-desc"><?php echo htmlspecialchars(substr($c['description'], 0, 150)) . (strlen($c['description']) > 150 ? '...' : ''); ?></p>
                
                <div class="complaint-meta">
                    <span><i class="fas fa-tags"></i> <?php echo $c['category_name']; ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $c['ward_name']; ?></span>
                    <span><i class="fas fa-calendar-check"></i> Resolved: <?php echo $c['resolved_at'] ? date('M d, Y', strtotime($c['resolved_at'])) : '-'; ?></span>
                </div>
                
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                    <small style="color: var(--primary-400);">
                        <i class="fas fa-user"></i> Complainant: <?php echo htmlspecialchars($c['complainant_name']); ?>
                    </small>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="glass-card empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h4 class="empty-state-title">No completed complaints yet</h4>
            <p>You haven't resolved any complaints. Go to "My Work" to start working on assigned complaints.</p>
            <a href="staff.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-tasks"></i> Go to My Work
            </a>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
