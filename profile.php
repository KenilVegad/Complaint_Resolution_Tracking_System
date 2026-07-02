<?php
/**
 * User Profile Page
 * View and edit profile information
 */
session_start();
require_once("config.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if(isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if(empty($name)) {
        $error = "Name is required";
    } else {
        // Start with basic update
        $updateFields = "name = ?, phone = ?";
        $params = [$name, $phone];
        $types = "ss";
        
        // Check if password change requested
        if(!empty($newPassword)) {
            if(empty($currentPassword)) {
                $error = "Current password is required to change password";
            } elseif(!password_verify($currentPassword, $user['password_hash'])) {
                $error = "Current password is incorrect";
            } elseif($newPassword !== $confirmPassword) {
                $error = "New passwords do not match";
            } elseif(strlen($newPassword) < 8) {
                $error = "Password must be at least 8 characters";
            } else {
                // Add password to update
                $updateFields .= ", password_hash = ?";
                $params[] = password_hash($newPassword, PASSWORD_BCRYPT);
                $types .= "s";
            }
        }
        
        if(empty($error)) {
            $params[] = $userId;
            $types .= "i";
            
            $stmt = $conn->prepare("UPDATE users SET $updateFields WHERE user_id = ?");
            $stmt->bind_param($types, ...$params);
            
            if($stmt->execute()) {
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
            } else {
                $error = "Error updating profile";
            }
            $stmt->close();
        }
    }
}

// Get complaint statistics for this user
$stats = [];
if($role === 'complainant') {
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('in_progress', 'assigned') THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints WHERE complainant_id = $userId");
    $stats = $result->fetch_assoc();
} elseif($role === 'staff') {
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints WHERE assigned_to = $userId");
    $stats = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            color: white;
        }
        .profile-info p {
            margin: 0;
            color: var(--primary-300);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-cyan);
        }
        .stat-label {
            color: var(--primary-300);
            font-size: 0.9rem;
        }
        .form-section {
            margin-bottom: 2rem;
        }
        .form-section h3 {
            color: var(--accent-cyan);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .password-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            color: var(--primary-300);
            cursor: pointer;
        }
        .password-toggle input {
            cursor: pointer;
        }
        .password-fields {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .password-fields.show {
            display: block;
        }
    </style>
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
                <?php if($role === 'supervisor'): ?>
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="admin_complaints.php" class="nav-link"><i class="fas fa-clipboard-list"></i> All Complaints</a></li>
                <li class="nav-item"><a href="staff_performance.php" class="nav-link"><i class="fas fa-chart-bar"></i> Staff Performance</a></li>
                <li class="nav-item"><a href="staff_management.php" class="nav-link"><i class="fas fa-users-cog"></i> Staff Management</a></li>
                <li class="nav-item"><a href="heatmap.php" class="nav-link"><i class="fas fa-fire"></i> Priority Heatmap</a></li>
                <li class="nav-item"><a href="area_master.php" class="nav-link"><i class="fas fa-map"></i> Area Master</a></li>
                <li class="nav-item"><a href="category_master.php" class="nav-link"><i class="fas fa-tags"></i> Categories</a></li>
                <li class="divider"></li>
                <li class="nav-item"><a href="api/pending_by_area.php" target="_blank" class="nav-link" style="color: var(--accent-cyan);"><i class="fas fa-code"></i> API: Pending by Area</a></li>
                <?php elseif($role === 'staff'): ?>
                <li class="nav-item"><a href="staff.php" class="nav-link"><i class="fas fa-hard-hat"></i> My Work</a></li>
                <li class="nav-item"><a href="staff_history.php" class="nav-link"><i class="fas fa-history"></i> History</a></li>
                <?php else: ?>
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="nav-item"><a href="complaint.php" class="nav-link"><i class="fas fa-plus-circle"></i> Register Complaint</a></li>
                <li class="nav-item"><a href="view_complaints.php" class="nav-link"><i class="fas fa-clipboard-list"></i> My Complaints</a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="track.php" class="nav-link"><i class="fas fa-search-location"></i> Track Complaint</a></li>
                
                <li class="divider"></li>
                
                <li class="nav-item"><a href="profile.php" class="nav-link active"><i class="fas fa-user-circle"></i> My Profile</a></li>
                <li class="nav-item"><a href="logout.php" class="nav-link" style="color: #ff416c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-user-circle" style="color: var(--accent-cyan);"></i> My Profile</h1>
            <p class="page-subtitle">Manage your account settings</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="glass-card" style="padding: 2rem; margin-bottom: 1.5rem;">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p style="margin-top: 0.5rem;">
                        <span class="role-badge role-<?php echo $role; ?>">
                            <i class="fas fa-<?php echo $role === 'supervisor' ? 'user-shield' : ($role === 'staff' ? 'hard-hat' : 'user'); ?>"></i>
                            <?php echo ucfirst($role); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <?php if(!empty($stats)): ?>
            <div class="stats-grid">
                <?php if($role === 'complainant'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent-yellow);"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent-cyan);"><?php echo $stats['in_progress'] ?? 0; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent-green);"><?php echo $stats['resolved'] ?? 0; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
                <?php elseif($role === 'staff'): ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Assigned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent-cyan);"><?php echo $stats['active'] ?? 0; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: var(--accent-green);"><?php echo $stats['resolved'] ?? 0; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Edit Profile Form -->
        <div class="glass-card" style="padding: 2rem;">
            <form method="POST">
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            <small style="color: var(--primary-400);">Email cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-lock"></i> Change Password</h3>
                    
                    <label class="password-toggle">
                        <input type="checkbox" id="changePasswordToggle">
                        <span>I want to change my password</span>
                    </label>
                    
                    <div class="password-fields" id="passwordFields">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" name="update_profile" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="<?php echo $role === 'supervisor' ? 'admin.php' : ($role === 'staff' ? 'staff.php' : 'dashboard.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </main>
    
    <script>
        document.getElementById('changePasswordToggle').addEventListener('change', function() {
            document.getElementById('passwordFields').classList.toggle('show', this.checked);
        });
    </script>
</body>
</html>
