<?php
/**
 * Priority Heatmap - Extra Feature
 * Visual representation of complaint density by ward/area
 */
session_start();
require_once("config.php");

if($_SESSION['role'] !== 'supervisor') {
    header("Location: dashboard.php");
    exit();
}

// Get complaint density by ward
$wardData = $conn->query("SELECT 
    w.area_id,
    w.area_name,
    COUNT(c.complaint_id) as complaint_count,
    SUM(CASE WHEN c.priority = 'critical' THEN 3 
             WHEN c.priority = 'high' THEN 2 
             WHEN c.priority = 'medium' THEN 1 
             ELSE 0.5 END) as priority_score
FROM area_master w
LEFT JOIN complaints c ON c.ward_id = w.area_id AND c.status NOT IN ('resolved', 'closed')
WHERE w.area_type = 'ward' AND w.is_active = 1
GROUP BY w.area_id, w.area_name
ORDER BY priority_score DESC");

// Get area-level data for selected ward
$selectedWard = isset($_GET['ward_id']) ? intval($_GET['ward_id']) : 0;
$areaData = [];
if($selectedWard) {
    $stmt = $conn->prepare("SELECT 
        a.area_id,
        a.area_name,
        COUNT(c.complaint_id) as complaint_count,
        SUM(CASE WHEN c.priority = 'critical' THEN 3 
                 WHEN c.priority = 'high' THEN 2 
                 WHEN c.priority = 'medium' THEN 1 
                 ELSE 0.5 END) as priority_score
    FROM area_master a
    LEFT JOIN complaints c ON c.area_id = a.area_id AND c.status NOT IN ('resolved', 'closed')
    WHERE a.parent_id = ? AND a.area_type = 'area' AND a.is_active = 1
    GROUP BY a.area_id, a.area_name
    ORDER BY priority_score DESC");
    $stmt->bind_param("i", $selectedWard);
    $stmt->execute();
    $areaData = $stmt->get_result();
    $stmt->close();
}

// Category breakdown
$categoryData = $conn->query("SELECT 
    cc.category_name,
    COUNT(c.complaint_id) as count,
    ROUND(COUNT(c.complaint_id) * 100.0 / (SELECT COUNT(*) FROM complaints), 1) as percentage
FROM complaint_categories cc
LEFT JOIN complaints c ON c.category_id = cc.category_id AND c.status NOT IN ('resolved', 'closed')
WHERE cc.is_active = 1
GROUP BY cc.category_id, cc.category_name
ORDER BY count DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Priority Heatmap | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .heatmap-cell {
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            transition: transform 0.3s;
            cursor: pointer;
        }
        .heatmap-cell:hover {
            transform: scale(1.05);
        }
        .heat-low { background: linear-gradient(135deg, #10b981, #059669); }
        .heat-medium { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .heat-high { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .heat-critical { background: linear-gradient(135deg, #7c3aed, #5b21b6); box-shadow: 0 0 20px rgba(124, 58, 237, 0.5); }
        .heat-none { background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); }
        .heat-count { font-size: 2.5rem; font-weight: 700; font-family: var(--font-display); }
        .heat-label { font-size: 0.9rem; opacity: 0.9; }
        .heat-level { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 0.5rem; }
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
                <li class="nav-item"><a href="heatmap.php" class="nav-link active"><i class="fas fa-fire"></i> Priority Heatmap</a></li>
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
            <h1 class="page-title"><i class="fas fa-fire" style="color: #f59e0b;"></i> Complaint Priority Heatmap</h1>
            <p class="page-subtitle">Visual representation of complaint density across wards and areas</p>
        </div>
        
        <!-- Legend -->
        <div class="glass-card" style="padding: 1rem; margin-bottom: 2rem;">
            <div style="display: flex; gap: 2rem; flex-wrap: wrap; justify-content: center;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; border-radius: 4px; background: linear-gradient(135deg, #10b981, #059669);"></div>
                    <span style="color: var(--primary-300);">Low (0-2 complaints)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; border-radius: 4px; background: linear-gradient(135deg, #f59e0b, #d97706);"></div>
                    <span style="color: var(--primary-300);">Medium (3-5 complaints)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; border-radius: 4px; background: linear-gradient(135deg, #ef4444, #dc2626);"></div>
                    <span style="color: var(--primary-300);">High (6-10 complaints)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 20px; height: 20px; border-radius: 4px; background: linear-gradient(135deg, #7c3aed, #5b21b6);"></div>
                    <span style="color: var(--primary-300);">Critical (10+ complaints)</span>
                </div>
            </div>
        </div>
        
        <!-- Ward Level Heatmap -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-map-marker-alt" style="color: var(--accent-cyan);"></i> Ward-Level Heatmap (Click to view areas)
            </h3>
            <div class="heatmap-grid">
                <?php while($ward = $wardData->fetch_assoc()):
                    $count = (int)$ward['complaint_count'];
                    $score = (float)$ward['priority_score'];
                    
                    if($count == 0) $heatClass = 'heat-none';
                    elseif($score <= 2) $heatClass = 'heat-low';
                    elseif($score <= 5) $heatClass = 'heat-medium';
                    elseif($score <= 10) $heatClass = 'heat-high';
                    else $heatClass = 'heat-critical';
                    
                    $levelText = $count == 0 ? 'No Active Complaints' : 
                                ($heatClass == 'heat-low' ? 'Low Priority' : 
                                ($heatClass == 'heat-medium' ? 'Medium Priority' : 
                                ($heatClass == 'heat-high' ? 'High Priority' : 'Critical Priority')));
                ?>
                <a href="?ward_id=<?php echo $ward['area_id']; ?>" class="heatmap-cell <?php echo $heatClass; ?>" style="text-decoration: none; color: white;">
                    <div class="heat-count"><?php echo $count; ?></div>
                    <div class="heat-label"><?php echo htmlspecialchars($ward['area_name']); ?></div>
                    <div class="heat-level"><?php echo $levelText; ?></div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Area Level Detail (if ward selected) -->
        <?php if($selectedWard && $areaData && $areaData->num_rows > 0): 
            $wardInfo = $conn->query("SELECT area_name FROM area_master WHERE area_id = $selectedWard")->fetch_assoc();
        ?>
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-family: var(--font-display); color: white; margin: 0;">
                    <i class="fas fa-city" style="color: var(--accent-green);"></i> Areas in <?php echo htmlspecialchars($wardInfo['area_name']); ?>
                </h3>
                <a href="heatmap.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">
                    <i class="fas fa-arrow-left"></i> Back to All Wards
                </a>
            </div>
            <div class="heatmap-grid">
                <?php while($area = $areaData->fetch_assoc()):
                    $count = (int)$area['complaint_count'];
                    $score = (float)$area['priority_score'];
                    
                    if($count == 0) $heatClass = 'heat-none';
                    elseif($score <= 2) $heatClass = 'heat-low';
                    elseif($score <= 5) $heatClass = 'heat-medium';
                    elseif($score <= 10) $heatClass = 'heat-high';
                    else $heatClass = 'heat-critical';
                ?>
                <div class="heatmap-cell <?php echo $heatClass; ?>">
                    <div class="heat-count"><?php echo $count; ?></div>
                    <div class="heat-label"><?php echo htmlspecialchars($area['area_name']); ?></div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Category Breakdown -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-tags" style="color: var(--accent-orange);"></i> Complaint Categories Distribution
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <?php while($cat = $categoryData->fetch_assoc()):
                    $percentage = (float)$cat['percentage'];
                    $barColor = $percentage > 20 ? '#dc2626' : ($percentage > 10 ? '#f59e0b' : '#10b981');
                ?>
                <div style="background: rgba(255,255,255,0.05); border-radius: var(--radius-md); padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: white; font-weight: 500;"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                        <span style="color: var(--accent-cyan); font-weight: 600;"><?php echo $cat['count']; ?> (<?php echo $percentage; ?>%)</span>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 8px;">
                        <div style="background: <?php echo $barColor; ?>; width: <?php echo $percentage; ?>%; height: 100%; border-radius: 10px; transition: width 0.5s;"></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <!-- Info Banner -->
        <div class="glass-card" style="padding: 1rem; margin-top: 2rem; background: rgba(0, 212, 255, 0.1);">
            <p style="margin: 0; color: var(--accent-cyan);">
                <i class="fas fa-lightbulb"></i> <strong>Extra Feature:</strong> This heatmap provides at-a-glance spatial insight for supervisors to identify high-priority areas needing immediate attention.
            </p>
        </div>
    </main>
</body>
</html>
