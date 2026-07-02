<?php
session_start();
require_once("config.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Search logic
$search = "";

if(isset($_GET['search'])){
    $search = sanitize($_GET['search']);
    $query = "SELECT c.*, cat.category_name, w.area_name as ward_name, a.area_name as area_name, s.area_name as spot_name
              FROM complaints c
              JOIN complaint_categories cat ON c.category_id = cat.category_id
              LEFT JOIN area_master w ON c.ward_id = w.area_id
              LEFT JOIN area_master a ON c.area_id = a.area_id
              LEFT JOIN area_master s ON c.spot_id = s.area_id
              WHERE c.complainant_id='$user_id' 
              AND (c.title LIKE '%$search%' OR c.status LIKE '%$search%' OR c.complaint_code LIKE '%$search%')
              ORDER BY c.complaint_id DESC";
} else {
    $query = "SELECT c.*, cat.category_name, w.area_name as ward_name, a.area_name as area_name, s.area_name as spot_name
              FROM complaints c
              JOIN complaint_categories cat ON c.category_id = cat.category_id
              LEFT JOIN area_master w ON c.ward_id = w.area_id
              LEFT JOIN area_master a ON c.area_id = a.area_id
              LEFT JOIN area_master s ON c.spot_id = s.area_id
              WHERE c.complainant_id='$user_id'
              ORDER BY c.complaint_id DESC";
}

$result = mysqli_query($conn, $query);
$count = mysqli_num_rows($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints | Citizen Complaint Portal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
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
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="complaint.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i> Register Complaint
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_complaints.php" class="nav-link active">
                        <i class="fas fa-clipboard-list"></i> My Complaints
                    </a>
                </li>
                <li class="nav-item">
                    <a href="track.php" class="nav-link">
                        <i class="fas fa-search-location"></i> Track Complaint
                    </a>
                </li>
                
                <?php if($role == 'admin'): ?>
                <li class="nav-item">
                    <a href="admin_complaints.php" class="nav-link">
                        <i class="fas fa-cogs"></i> Manage All
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
            <h1 class="page-title">My Complaints</h1>
            <p class="page-subtitle">Track and manage your submitted complaints</p>
        </div>
        
        <!-- Search Bar -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <div class="input-icon-wrapper">
                        <i class="fas fa-search input-icon"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search by title or status..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if($search): ?>
                <a href="view_complaints.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Results Count -->
        <div style="margin-bottom: 1.5rem; color: var(--primary-300);">
            <i class="fas fa-list"></i> Showing <strong style="color: var(--accent-cyan);"><?php echo $count; ?></strong> complaint<?php echo $count != 1 ? 's' : ''; ?>
        </div>
        
        <!-- Complaints Grid -->
        <?php if($count > 0): ?>
        <div class="cards-grid">
            <?php while($row = mysqli_fetch_assoc($result)) { 
                $status_class = strtolower(str_replace(' ', '-', $row['status']));
            ?>
            <div class="glass-card complaint-card fade-in">
                <div class="complaint-header">
                    <div>
                        <span class="complaint-id"><?php echo $row['complaint_code']; ?></span>
                        <h3 class="complaint-title" style="margin-top: 0.5rem;"><?php echo htmlspecialchars($row['title']); ?></h3>
                    </div>
                    <span class="status-badge status-<?php echo $status_class; ?>">
                        <?php echo $row['status']; ?>
                    </span>
                </div>
                
                <p class="complaint-desc"><?php echo htmlspecialchars(substr($row['description'], 0, 150)) . (strlen($row['description']) > 150 ? '...' : ''); ?></p>
                
                <div class="complaint-meta">
                    <span><i class="fas fa-tags"></i> <?php echo $row['category_name']; ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo $row['ward_name']; ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['submitted_at'])); ?></span>
                </div>
                
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                    <small style="color: var(--primary-400);">
                        <i class="fas fa-location-arrow"></i> 
                        <?php echo $row['area_name'] . ' - ' . $row['spot_name']; ?>
                    </small>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php else: ?>
        <div class="glass-card empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-inbox"></i>
            </div>
            <h4 class="empty-state-title">No complaints found</h4>
            <p><?php echo $search ? 'Try different search terms' : 'You haven\'t submitted any complaints yet'; ?></p>
            <a href="complaint.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Register Your First Complaint
            </a>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>