<?php
/**
 * Complaint Category Master
 * Manage Road/Pathway Surface Damage categories
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

// Handle add/edit category
if(isset($_POST['save_category'])) {
    $categoryId = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $categoryName = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    if(empty($categoryName)) {
        $error = "Category name is required";
    } else {
        if($categoryId) {
            // Update existing
            $stmt = $conn->prepare("UPDATE complaint_categories SET category_name = ?, description = ?, is_active = ? WHERE category_id = ?");
            $stmt->bind_param("ssii", $categoryName, $description, $isActive, $categoryId);
        } else {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO complaint_categories (category_name, description, is_active) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $categoryName, $description, $isActive);
        }
        
        if($stmt->execute()) {
            $success = $categoryId ? "Category updated successfully!" : "Category created successfully!";
        } else {
            $error = "Error saving category: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle delete
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $categoryId = intval($_GET['delete']);
    
    // Check if category is in use
    $check = $conn->prepare("SELECT COUNT(*) as count FROM complaints WHERE category_id = ?");
    $check->bind_param("i", $categoryId);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();
    
    if($result['count'] > 0) {
        $error = "Cannot delete: This category is used in complaints. Deactivate it instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM complaint_categories WHERE category_id = ?");
        $stmt->bind_param("i", $categoryId);
        if($stmt->execute()) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Error deleting category";
        }
        $stmt->close();
    }
}

// Handle toggle status (Enable/Disable)
if(isset($_POST['toggle_status'])) {
    $categoryId = intval($_POST['category_id']);
    $currentStatus = intval($_POST['is_active']);
    $newStatus = $currentStatus ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE complaint_categories SET is_active = ? WHERE category_id = ?");
    $stmt->bind_param("ii", $newStatus, $categoryId);
    
    if($stmt->execute()) {
        $success = $newStatus ? "Category enabled successfully!" : "Category disabled successfully!";
    } else {
        $error = "Error toggling category status";
    }
    $stmt->close();
}

// Get all categories
$categories = $conn->query("SELECT * FROM complaint_categories ORDER BY is_active DESC, category_name");

// Get category usage count
$usageQuery = $conn->query("SELECT category_id, COUNT(*) as count FROM complaints GROUP BY category_id");
$usage = [];
while($u = $usageQuery->fetch_assoc()) {
    $usage[$u['category_id']] = $u['count'];
}

// Edit mode
$editCategory = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM complaint_categories WHERE category_id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $editCategory = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Master | <?php echo DOMAIN_NAME; ?></title>
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
                <li class="nav-item"><a href="area_master.php" class="nav-link"><i class="fas fa-map"></i> Area Master</a></li>
                <li class="nav-item"><a href="category_master.php" class="nav-link active"><i class="fas fa-tags"></i> Categories</a></li>
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
            <h1 class="page-title"><i class="fas fa-tags" style="color: var(--accent-orange);"></i> Category Master</h1>
            <p class="page-subtitle">Manage <?php echo DOMAIN_NAME; ?> complaint categories</p>
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
                    <i class="fas fa-<?php echo $editCategory ? 'edit' : 'plus'; ?>" style="color: var(--accent-cyan);"></i>
                    <?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?>
                </h3>
                
                <form method="POST">
                    <?php if($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?php echo $editCategory['category_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" class="form-control" 
                            value="<?php echo $editCategory ? htmlspecialchars($editCategory['category_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe what this category covers..."><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" value="1" 
                                <?php echo (!$editCategory || $editCategory['is_active']) ? 'checked' : ''; ?>>
                            <span style="color: var(--primary-200);">Active</span>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="save_category" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-save"></i> <?php echo $editCategory ? 'Update' : 'Save'; ?>
                        </button>
                        <?php if($editCategory): ?>
                        <a href="category_master.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Category List -->
            <div class="glass-card" style="padding: 1.5rem;">
                <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                    <i class="fas fa-list" style="color: var(--accent-purple);"></i> Categories
                </h3>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($cat = $categories->fetch_assoc()): 
                                $count = $usage[$cat['category_id']] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cat['category_name']); ?></strong></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo $cat['description'] ? htmlspecialchars(substr($cat['description'], 0, 50)) . '...' : '-'; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span style="background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 10px; font-size: 0.85rem;">
                                        <?php echo $count; ?> complaints
                                    </span>
                                </td>
                                <td>
                                    <?php if($cat['is_active']): ?>
                                    <span style="color: #10b981;"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                    <span style="color: var(--primary-400);"><i class="fas fa-times-circle"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $cat['category_id']; ?>" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('<?php echo $cat['is_active'] ? 'Disable' : 'Enable'; ?> this category?')">
                                        <input type="hidden" name="category_id" value="<?php echo $cat['category_id']; ?>">
                                        <input type="hidden" name="is_active" value="<?php echo $cat['is_active']; ?>">
                                        <?php if($cat['is_active']): ?>
                                        <button type="submit" name="toggle_status" class="btn btn-warning" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #f59e0b; color: white;">
                                            <i class="fas fa-ban"></i> Disable
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" name="toggle_status" class="btn btn-success" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #10b981; color: white;">
                                            <i class="fas fa-check"></i> Enable
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                    <?php if($count == 0): ?>
                                    <a href="?delete=<?php echo $cat['category_id']; ?>" class="btn btn-danger" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;" 
                                       onclick="return confirm('Delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
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
