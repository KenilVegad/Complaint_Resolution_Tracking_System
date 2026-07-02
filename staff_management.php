<?php
/**
 * Staff Management Page
 * Add, edit, and manage staff members
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

// Handle Add Staff
if(isset($_POST['add_staff'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    
    if(empty($name) || empty($email) || empty($password)) {
        $error = "Name, email, and password are required.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if($check->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role, phone, is_active) VALUES (?, ?, ?, 'staff', ?, 1)");
            $stmt->bind_param("ssss", $name, $email, $passwordHash, $phone);
            
            if($stmt->execute()) {
                $success = "Staff member '{$name}' added successfully!";
            } else {
                $error = "Failed to add staff member.";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle Toggle Status
if(isset($_GET['toggle']) && isset($_GET['id'])) {
    $staffId = intval($_GET['id']);
    $newStatus = intval($_GET['toggle']);
    
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ? AND role = 'staff'");
    $stmt->bind_param("ii", $newStatus, $staffId);
    if($stmt->execute()) {
        $success = "Staff status updated.";
    }
    $stmt->close();
    header("Location: staff_management.php");
    exit();
}

// Fetch all staff
$staffQuery = "SELECT u.* 
               FROM users u 
               WHERE u.role = 'staff' 
               ORDER BY u.name";
$staffList = $conn->query($staffQuery);

$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Admin Portal</title>
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
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="admin_complaints.php" class="nav-link"><i class="fas fa-clipboard-list"></i> All Complaints</a></li>
                <li class="nav-item"><a href="staff_performance.php" class="nav-link"><i class="fas fa-chart-bar"></i> Staff Performance</a></li>
                <li class="nav-item"><a href="staff_management.php" class="nav-link active"><i class="fas fa-users-cog"></i> Staff Management</a></li>
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
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-users-cog"></i> Staff Management</h1>
            <p class="page-subtitle">Add and manage staff members</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem;">
            <!-- Add Staff Form -->
            <div class="glass-card" style="padding: 2rem; height: fit-content;">
                <h3 style="color: var(--accent-purple); margin-bottom: 1.5rem;">
                    <i class="fas fa-user-plus"></i> Add New Staff
                </h3>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter staff name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="staff@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="9876543210">
                    </div>
                    
                    <button type="submit" name="add_staff" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Add Staff Member
                    </button>
                </form>
            </div>
            
            <!-- Staff List -->
            <div class="glass-card" style="padding: 2rem;">
                <h3 style="color: var(--accent-cyan); margin-bottom: 1.5rem;">
                    <i class="fas fa-users"></i> All Staff Members
                </h3>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                                <th style="padding: 1rem; text-align: left; color: var(--primary-300);">ID</th>
                                <th style="padding: 1rem; text-align: left; color: var(--primary-300);">Name</th>
                                <th style="padding: 1rem; text-align: left; color: var(--primary-300);">Email</th>
                                <th style="padding: 1rem; text-align: left; color: var(--primary-300);">Phone</th>
                                <th style="padding: 1rem; text-align: center; color: var(--primary-300);">Status</th>
                                <th style="padding: 1rem; text-align: center; color: var(--primary-300);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($staffList->num_rows > 0): ?>
                                <?php while($staff = $staffList->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 1rem;"><?php echo $staff['user_id']; ?></td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td style="padding: 1rem;"><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td style="padding: 1rem;"><?php echo $staff['phone'] ?: '-'; ?></td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php if($staff['is_active']): ?>
                                            <span style="background: rgba(16, 185, 129, 0.2); color: #10b981; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem;">Active</span>
                                        <?php else: ?>
                                            <span style="background: rgba(220, 38, 38, 0.2); color: #dc2626; padding: 4px 12px; border-radius: 12px; font-size: 0.8rem;">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <?php if($staff['is_active']): ?>
                                            <a href="staff_management.php?toggle=0&id=<?php echo $staff['user_id']; ?>" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" onclick="return confirm('Deactivate this staff member?')">
                                                <i class="fas fa-ban"></i> Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="staff_management.php?toggle=1&id=<?php echo $staff['user_id']; ?>" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                                <i class="fas fa-check"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="padding: 2rem; text-align: center; color: var(--primary-400);">
                                        <i class="fas fa-info-circle"></i> No staff members found. Add your first staff above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
