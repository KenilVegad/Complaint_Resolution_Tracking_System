<?php
/**
 * Area Master Management
 * Manage Ward → Area → Spot hierarchy
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

// Handle add/edit area
if(isset($_POST['save_area'])) {
    $areaId = !empty($_POST['area_id']) ? intval($_POST['area_id']) : null;
    $areaName = sanitize($_POST['area_name']);
    $areaType = sanitize($_POST['area_type']);
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if(empty($areaName)) {
        $error = "Area name is required";
    } else {
        if($areaId) {
            // Update existing
            $stmt = $conn->prepare("UPDATE area_master SET area_name = ?, area_type = ?, parent_id = ?, is_active = ? WHERE area_id = ?");
            $stmt->bind_param("ssiii", $areaName, $areaType, $parentId, $isActive, $areaId);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $areaName, $areaType, $parentId, $isActive);
        }
        
        if($stmt->execute()) {
            $success = $areaId ? "Area updated successfully!" : "Area created successfully!";
        } else {
            $error = "Error saving area: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $areaId = intval($_GET['delete']);
    
    // Check if area has children
    $check = $conn->prepare("SELECT COUNT(*) as count FROM area_master WHERE parent_id = ?");
    $check->bind_param("i", $areaId);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();
    
    if($result['count'] > 0) {
        $error = "Cannot delete: This area has child areas. Delete children first.";
    } else {
        $stmt = $conn->prepare("DELETE FROM area_master WHERE area_id = ?");
        $stmt->bind_param("i", $areaId);
        if($stmt->execute()) {
            $success = "Area deleted successfully!";
        } else {
            $error = "Error deleting area";
        }
        $stmt->close();
    }
}

// Handle toggle status (Enable/Disable)
if(isset($_POST['toggle_status'])) {
    $areaId = intval($_POST['area_id']);
    $currentStatus = intval($_POST['is_active']);
    $newStatus = $currentStatus ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE area_master SET is_active = ? WHERE area_id = ?");
    $stmt->bind_param("ii", $newStatus, $areaId);
    
    if($stmt->execute()) {
        $success = $newStatus ? "Area enabled successfully!" : "Area disabled successfully!";
    } else {
        $error = "Error toggling area status";
    }
    $stmt->close();
}

// Get all areas with hierarchy
$areas = $conn->query("SELECT a.*, p.area_name as parent_name 
    FROM area_master a 
    LEFT JOIN area_master p ON a.parent_id = p.area_id 
    ORDER BY a.area_type, a.area_name");

// Get wards for dropdown
$wards = $conn->query("SELECT area_id, area_name FROM area_master WHERE area_type = 'ward' AND is_active = 1 ORDER BY area_name");

// Get areas for dropdown
$areasList = $conn->query("SELECT area_id, area_name, parent_id FROM area_master WHERE area_type = 'area' AND is_active = 1 ORDER BY area_name");

// Edit mode
$editArea = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM area_master WHERE area_id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editArea = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Area Master | Complaint Management System</title>
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
                <li class="nav-item"><a href="admin_complaints.php" class="nav-link"><i class="fas fa-clipboard-list"></i> All Complaints</a></li>
                <li class="nav-item"><a href="staff_performance.php" class="nav-link"><i class="fas fa-chart-bar"></i> Staff Performance</a></li>
                <li class="nav-item"><a href="staff_management.php" class="nav-link"><i class="fas fa-users-cog"></i> Staff Management</a></li>
                <li class="nav-item"><a href="heatmap.php" class="nav-link"><i class="fas fa-fire"></i> Priority Heatmap</a></li>
                <li class="nav-item"><a href="area_master.php" class="nav-link active"><i class="fas fa-map"></i> Area Master</a></li>
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
            <h1 class="page-title"><i class="fas fa-map" style="color: var(--accent-cyan);"></i> Area Master</h1>
            <p class="page-subtitle">Manage Ward → Area → Spot hierarchy</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
            <!-- Add/Edit Form -->
            <div class="glass-card" style="padding: 1.5rem;">
                <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                    <i class="fas fa-<?php echo $editArea ? 'edit' : 'plus'; ?>" style="color: var(--accent-cyan);"></i>
                    <?php echo $editArea ? 'Edit Area' : 'Add New Area'; ?>
                </h3>
                
                <form method="POST">
                    <?php if($editArea): ?>
                    <input type="hidden" name="area_id" value="<?php echo $editArea['area_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Area Name *</label>
                        <input type="text" name="area_name" class="form-control" 
                            value="<?php echo $editArea ? htmlspecialchars($editArea['area_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Area Type *</label>
                        <select name="area_type" class="form-control" required>
                            <option value="ward" <?php echo ($editArea && $editArea['area_type'] == 'ward') ? 'selected' : ''; ?>>Ward</option>
                            <option value="area" <?php echo ($editArea && $editArea['area_type'] == 'area') ? 'selected' : ''; ?>>Area</option>
                            <option value="spot" <?php echo ($editArea && $editArea['area_type'] == 'spot') ? 'selected' : ''; ?>>Spot</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Parent (for Area/Spot)</label>
                        <select name="parent_id" id="parent_id" class="form-control">
                            <option value="">-- Select Parent --</option>
                            
                            <!-- Ward Options (for Area) -->
                            <optgroup label="Wards (for Area selection)" id="ward-options">
                                <?php 
                                $wards->data_seek(0);
                                while($w = $wards->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $w['area_id']; ?>" data-type="ward"
                                    <?php echo ($editArea && $editArea['area_type'] == 'area' && $editArea['parent_id'] == $w['area_id']) ? 'selected' : ''; ?>>
                                    🏙️ <?php echo htmlspecialchars($w['area_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </optgroup>
                            
                            <!-- Area Options (for Spot) -->
                            <optgroup label="Areas (for Spot selection)" id="area-options">
                                <?php 
                                $areasList->data_seek(0);
                                while($a = $areasList->fetch_assoc()): 
                                    // Get ward name for this area
                                    $wardName = '';
                                    foreach($wards as $w) {
                                        if($w['area_id'] == $a['parent_id']) {
                                            $wardName = $w['area_name'];
                                            break;
                                        }
                                    }
                                ?>
                                <option value="<?php echo $a['area_id']; ?>" data-type="area"
                                    <?php echo ($editArea && $editArea['area_type'] == 'spot' && $editArea['parent_id'] == $a['area_id']) ? 'selected' : ''; ?>>
                                    📍 <?php echo htmlspecialchars($a['area_name']); ?> <?php echo $wardName ? '(' . htmlspecialchars($wardName) . ')' : ''; ?>
                                </option>
                                <?php endwhile; ?>
                            </optgroup>
                        </select>
                        <small style="color: var(--primary-400);">
                            <span id="parent-hint">Select type first to see appropriate parents</span>
                        </small>
                    </div>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var typeSelect = document.querySelector('select[name="area_type"]');
                        var parentSelect = document.getElementById('parent_id');
                        var wardOptgroup = document.getElementById('ward-options');
                        var areaOptgroup = document.getElementById('area-options');
                        var hint = document.getElementById('parent-hint');
                        
                        function updateParentOptions() {
                            var type = typeSelect.value;
                            
                            if(type === 'ward') {
                                // No parent needed for ward
                                parentSelect.value = '';
                                parentSelect.disabled = true;
                                wardOptgroup.style.display = 'none';
                                areaOptgroup.style.display = 'none';
                                hint.textContent = 'Wards do not need a parent';
                            } else if(type === 'area') {
                                // Show only wards
                                parentSelect.disabled = false;
                                wardOptgroup.style.display = '';
                                areaOptgroup.style.display = 'none';
                                hint.textContent = 'Select the Ward this Area belongs to';
                            } else if(type === 'spot') {
                                // Show only areas
                                parentSelect.disabled = false;
                                wardOptgroup.style.display = 'none';
                                areaOptgroup.style.display = '';
                                hint.textContent = 'Select the Area this Spot belongs to';
                            }
                        }
                        
                        typeSelect.addEventListener('change', updateParentOptions);
                        updateParentOptions(); // Run on page load
                    });
                    </script>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" 
                                <?php echo (!$editArea || $editArea['is_active']) ? 'checked' : ''; ?>>
                            <span style="color: var(--primary-200);">Active</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="save_area" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> <?php echo $editArea ? 'Update' : 'Save'; ?>
                        </button>
                        <?php if($editArea): ?>
                        <a href="area_master.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Area List -->
            <div class="glass-card" style="padding: 1.5rem;">
                <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                    <i class="fas fa-list" style="color: var(--accent-purple);"></i> Area Hierarchy
                </h3>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Parent</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($area = $areas->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span style="text-transform: uppercase; font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: 
                                        <?php echo $area['area_type'] == 'ward' ? 'var(--accent-cyan)' : 
                                            ($area['area_type'] == 'area' ? 'var(--accent-purple)' : 'var(--accent-green)'); ?>;">
                                        <?php echo $area['area_type']; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($area['area_name']); ?></td>
                                <td><?php echo $area['parent_name'] ? htmlspecialchars($area['parent_name']) : '<em style="color: var(--primary-400);">-</em>'; ?></td>
                                <td>
                                    <?php if($area['is_active']): ?>
                                    <span style="color: #10b981;"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                    <span style="color: var(--primary-400);"><i class="fas fa-times-circle"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $area['area_id']; ?>" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('<?php echo $area['is_active'] ? 'Disable' : 'Enable'; ?> this area?')">
                                        <input type="hidden" name="area_id" value="<?php echo $area['area_id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $area['is_active']; ?>">
                                        <?php if($area['is_active']): ?>
                                        <button type="submit" name="toggle_status" class="btn btn-warning" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #f59e0b; color: white;">
                                            <i class="fas fa-ban"></i> Disable
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" name="toggle_status" class="btn btn-success" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #10b981; color: white;">
                                            <i class="fas fa-check"></i> Enable
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="?delete=<?php echo $area['area_id']; ?>" class="btn btn-danger" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" 
                                       onclick="return confirm('Delete this area?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
