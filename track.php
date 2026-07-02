<?php
session_start();
require_once("config.php");

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? $_SESSION['role'] : null;
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// All logged-in users can access track page (complainants, staff, supervisors)
// Public users can also access but with limited info
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaint | Citizen Complaint Portal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    
    <?php if ($is_logged_in): ?>
    <!-- Mobile Toggle -->
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar-premium').classList.toggle('active')">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar for logged-in users -->
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
                <?php if ($role == 'supervisor'): ?>
                <li class="nav-item">
                    <a href="admin.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <!-- Full Admin Menu -->
                <li class="nav-item">
                    <a href="admin_complaints.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i> All Complaints
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff_performance.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i> Staff Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff_management.php" class="nav-link">
                        <i class="fas fa-users-cog"></i> Staff Management
                    </a>
                </li>
                <li class="nav-item">
                    <a href="heatmap.php" class="nav-link">
                        <i class="fas fa-fire"></i> Priority Heatmap
                    </a>
                </li>
                <li class="nav-item">
                    <a href="area_master.php" class="nav-link">
                        <i class="fas fa-map"></i> Area Master
                    </a>
                </li>
                <li class="nav-item">
                    <a href="category_master.php" class="nav-link">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($role == 'staff'): ?>
                <li class="nav-item">
                    <a href="staff.php" class="nav-link">
                        <i class="fas fa-tasks"></i> My Work
                    </a>
                </li>
                <li class="nav-item">
                    <a href="staff_history.php" class="nav-link">
                        <i class="fas fa-history"></i> History
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($role == 'complainant'): ?>
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
                    <a href="view_complaints.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i> My Complaints
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a href="track.php" class="nav-link active">
                        <i class="fas fa-search-location"></i> Track Complaint
                    </a>
                </li>
                
                <?php if ($role == 'supervisor'): ?>
                <li class="divider"></li>
                <li class="nav-item">
                    <a href="api/pending_by_area.php" target="_blank" class="nav-link" style="color: var(--accent-cyan);">
                        <i class="fas fa-code"></i> API: Pending by Area
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
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="main-content" <?php echo !$is_logged_in ? 'style="margin-left: 0;"' : ''; ?>>
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-search-location" style="color: var(--accent-cyan);"></i> 
                Track Your Complaint
            </h1>
            <p class="page-subtitle">
                <?php echo $is_logged_in 
                    ? 'Search and track any complaint by complaint code' 
                    : 'Enter your complaint code to check the status'; ?>
            </p>
        </div>
        
        <div class="glass-card" style="padding: 2rem; max-width: 700px;">
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-barcode" style="color: var(--accent-cyan);"></i> 
                    Enter Complaint Code
                </label>
                <div style="display: flex; gap: 1rem;">
                    <input type="text" id="complaint_code" class="form-control" 
                           placeholder="e.g. ROAD-2026-0001" style="flex: 1; text-transform: uppercase;">
                    <button onclick="track()" class="btn btn-primary">
                        <i class="fas fa-search"></i> Track
                    </button>
                </div>
                <small style="color: var(--primary-400); margin-top: 0.5rem; display: block;">
                    <i class="fas fa-info-circle"></i> 
                    Complaint code was provided when you submitted your complaint
                </small>
            </div>
            
            <div id="result" style="margin-top: 2rem;"></div>
        </div>
        
        <?php if (!$is_logged_in): ?>
        <div style="text-align: center; margin-top: 2rem;">
            <p style="color: var(--primary-400);">
                <i class="fas fa-user-circle"></i> Staff or Citizen? 
                <a href="login.php" style="color: var(--accent-cyan);">Login here</a>
            </p>
        </div>
        <?php endif; ?>
    </main>

<script>
const isPublic = <?php echo $is_logged_in ? 'false' : 'true'; ?>;

function track(){
    var code = document.getElementById("complaint_code").value.trim().toUpperCase();
    var resultDiv = document.getElementById("result");
    
    if(!code){
        resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Please enter a complaint code</div>';
        return;
    }
    
    resultDiv.innerHTML = '<div style="text-align: center; padding: 2rem;"><div class="loading"></div><p style="color: var(--primary-400); margin-top: 1rem;">Searching...</p></div>';

    var apiUrl = "api/track_complaint.php?code=" + encodeURIComponent(code);
    if (isPublic) {
        apiUrl += "&public=1";
    }

    $.ajax({
        url: apiUrl,
        dataType: "json",
        success: function(data){
            if(data.error){
                resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-times-circle"></i> ' + data.error + '. Please check the code and try again.</div>';
            } else {
                renderComplaintDetails(data, resultDiv);
            }
        },
        error: function(){
            resultDiv.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error connecting to server. Please try again.</div>';
        }
    });
}

function renderComplaintDetails(data, container) {
    var statusClass = data.status ? data.status.toLowerCase().replace(/\s+/g, '-') : 'submitted';
    var isResolved = data.status === 'resolved' || data.status === 'closed';
    var slaInfo = calculateSLA(data);
    
    var html = '<div class="glass-card" style="padding: 1.5rem; animation: fadeIn 0.5s;">';
    
    // Header with code and status
    html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">';
    html += '<div>';
    html += '<span style="font-family: var(--font-mono); color: var(--accent-cyan); font-size: 1.1rem;">' + data.complaint_code + '</span>';
    html += '<h3 style="color: white; margin-top: 0.5rem; font-family: var(--font-display);">' + data.title + '</h3>';
    html += '</div>';
    html += '<span class="status-badge status-' + statusClass + '" style="font-size: 0.9rem;">' + data.status.replace(/_/g, ' ').toUpperCase() + '</span>';
    html += '</div>';
    
    // Priority badge
    var priorityColors = {
        'low': '#00e676',
        'medium': '#ffd600',
        'high': '#ff9100',
        'critical': '#ff416c'
    };
    html += '<div style="margin-bottom: 1.5rem;">';
    html += '<span style="background: rgba(255,255,255,0.1); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; text-transform: uppercase; border-left: 3px solid ' + (priorityColors[data.priority] || '#666') + ';">';
    html += '<i class="fas fa-flag"></i> ' + data.priority.toUpperCase() + ' PRIORITY';
    html += '</span>';
    html += '</div>';
    
    // Description
    html += '<p style="color: var(--primary-300); margin-bottom: 1.5rem; line-height: 1.6;">' + data.description + '</p>';
    
    // Location info
    html += '<div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;">';
    html += '<h4 style="color: var(--primary-200); margin-bottom: 0.75rem; font-size: 0.9rem;"><i class="fas fa-map-marked-alt"></i> Location Details</h4>';
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; font-size: 0.9rem;">';
    html += '<div><span style="color: var(--primary-400);">Ward:</span> <span style="color: white;">' + (data.ward_name || 'N/A') + '</span></div>';
    html += '<div><span style="color: var(--primary-400);">Area:</span> <span style="color: white;">' + (data.area_name || 'N/A') + '</span></div>';
    html += '<div><span style="color: var(--primary-400);">Spot:</span> <span style="color: white;">' + (data.spot_name || 'N/A') + '</span></div>';
    html += '</div>';
    if (data.exact_location) {
        html += '<div style="margin-top: 0.5rem; color: var(--primary-300); font-size: 0.85rem;"><i class="fas fa-location-arrow"></i> ' + data.exact_location + '</div>';
    }
    html += '</div>';
    
    // SLA Info
    html += '<div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem;">';
    html += '<h4 style="color: var(--primary-200); margin-bottom: 0.75rem; font-size: 0.9rem;"><i class="fas fa-clock"></i> SLA Timeline</h4>';
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.85rem;">';
    html += '<div><span style="color: var(--primary-400);">Submitted:</span><br><span style="color: white;">' + formatDate(data.submitted_at) + '</span></div>';
    html += '<div><span style="color: var(--primary-400);">Initial SLA Deadline:</span><br><span style="color: ' + (slaInfo.initialOverdue ? '#ff416c' : 'white') + ';">' + formatDate(data.initial_sla_deadline) + '</span></div>';
    html += '<div><span style="color: var(--primary-400);">Resolution SLA:</span><br><span style="color: ' + (slaInfo.resolutionOverdue ? '#ff416c' : 'white') + ';">' + formatDate(data.resolution_sla_deadline) + '</span></div>';
    if (data.resolved_at) {
        html += '<div><span style="color: var(--primary-400);">Resolved:</span><br><span style="color: var(--accent-green);">' + formatDate(data.resolved_at) + '</span></div>';
    }
    html += '</div>';
    html += '</div>';
    
    // Category and Assignment
    html += '<div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">';
    html += '<div style="background: rgba(102, 126, 234, 0.1); padding: 0.5rem 1rem; border-radius: 8px;">';
    html += '<span style="color: var(--primary-400); font-size: 0.8rem;">Category</span><br>';
    html += '<span style="color: white; font-weight: 500;">' + (data.category_name || 'N/A') + '</span>';
    html += '</div>';
    html += '<div style="background: rgba(0, 212, 255, 0.1); padding: 0.5rem 1rem; border-radius: 8px;">';
    html += '<span style="color: var(--primary-400); font-size: 0.8rem;">Assigned To</span><br>';
    html += '<span style="color: white; font-weight: 500;">' + (data.assigned_staff_name || 'Pending') + '</span>';
    html += '</div>';
    html += '</div>';
    
    // Timeline (if available and logged in)
    if (!isPublic && data.timeline && data.timeline.length > 0) {
        html += '<div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border);">';
        html += '<h4 style="color: var(--primary-200); margin-bottom: 1rem; font-size: 0.9rem;"><i class="fas fa-history"></i> Status Timeline</h4>';
        html += '<div style="position: relative; padding-left: 20px;">';
        
        data.timeline.forEach(function(item, index) {
            var isLast = index === data.timeline.length - 1;
            html += '<div style="position: relative; padding-bottom: ' + (isLast ? '0' : '1rem') + ';">';
            html += '<div style="position: absolute; left: -16px; top: 4px; width: 8px; height: 8px; background: ' + (isLast ? 'var(--accent-cyan)' : 'var(--primary-500)') + '; border-radius: 50%;"></div>';
            if (!isLast) {
                html += '<div style="position: absolute; left: -13px; top: 16px; width: 2px; height: calc(100% - 8px); background: var(--glass-border);"></div>';
            }
            html += '<div style="background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: 8px;">';
            html += '<div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">';
            html += '<span style="color: white; font-weight: 500; text-transform: uppercase; font-size: 0.85rem;">' + item.status_name + '</span>';
            html += '<span style="color: var(--primary-400); font-size: 0.8rem;">' + formatDate(item.entered_at) + '</span>';
            html += '</div>';
            if (item.entered_by_name) {
                html += '<div style="color: var(--primary-400); font-size: 0.8rem; margin-top: 0.25rem;"><i class="fas fa-user"></i> ' + item.entered_by_name + '</div>';
            }
            if (item.remarks) {
                html += '<div style="color: var(--primary-300); font-size: 0.8rem; margin-top: 0.5rem; font-style: italic;"><i class="fas fa-comment"></i> ' + item.remarks + '</div>';
            }
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        html += '</div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function calculateSLA(data) {
    var now = new Date();
    var initialSLA = data.initial_sla_deadline ? new Date(data.initial_sla_deadline) : null;
    var resolutionSLA = data.resolution_sla_deadline ? new Date(data.resolution_sla_deadline) : null;
    
    return {
        initialOverdue: initialSLA && now > initialSLA && data.status !== 'resolved' && data.status !== 'closed',
        resolutionOverdue: resolutionSLA && now > resolutionSLA && data.status !== 'resolved' && data.status !== 'closed'
    };
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    var date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Allow Enter key to trigger search
document.getElementById("complaint_code").addEventListener("keypress", function(event) {
    if (event.key === "Enter") {
        track();
    }
});
</script>
</body>
</html>