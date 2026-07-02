<?php
/**
 * Staff Performance Summary Report (Mandatory Report R=3)
 * Enrollment: 230210107075 | U=75 | R=3
 */
session_start();
require_once("config.php");

if($_SESSION['role'] !== 'supervisor') {
    header("Location: dashboard.php");
    exit();
}

// Get staff performance data
$query = "SELECT 
    u.user_id,
    u.name as staff_name,
    u.email,
    u.phone,
    u.ward_id,
    w.area_name as assigned_ward,
    COUNT(DISTINCT c.complaint_id) as total_assigned,
    COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') THEN c.complaint_id END) as resolved_count,
    COUNT(DISTINCT CASE WHEN c.status = 'escalated' THEN c.complaint_id END) as escalation_count,
    COUNT(DISTINCT CASE WHEN c.status NOT IN ('resolved', 'closed') AND NOW() > c.resolution_sla_deadline THEN c.complaint_id END) as sla_breach_count,
    AVG(CASE WHEN c.status IN ('resolved', 'closed') THEN TIMESTAMPDIFF(HOUR, c.assigned_at, c.resolved_at) END) as avg_resolution_hours,
    MIN(CASE WHEN c.status IN ('resolved', 'closed') THEN TIMESTAMPDIFF(HOUR, c.assigned_at, c.resolved_at) END) as fastest_resolution,
    MAX(CASE WHEN c.status IN ('resolved', 'closed') THEN TIMESTAMPDIFF(HOUR, c.assigned_at, c.resolved_at) END) as slowest_resolution,
    AVG(f.rating) as avg_feedback_rating,
    COUNT(DISTINCT f.feedback_id) as feedback_count
FROM users u
LEFT JOIN complaints c ON c.assigned_to = u.user_id
LEFT JOIN area_master w ON u.ward_id = w.area_id
LEFT JOIN feedback f ON f.complaint_id = c.complaint_id
WHERE u.role = 'staff' AND u.is_active = 1
GROUP BY u.user_id, u.name, u.email, u.phone, u.ward_id, w.area_name
ORDER BY total_assigned DESC";

$staffPerformance = $conn->query($query);

// Overall statistics
$overallStats = $conn->query("SELECT 
    COUNT(DISTINCT c.complaint_id) as total_complaints,
    COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') THEN c.complaint_id END) as total_resolved,
    AVG(CASE WHEN c.status IN ('resolved', 'closed') THEN TIMESTAMPDIFF(HOUR, c.submitted_at, c.resolved_at) END) as overall_avg_resolution,
    COUNT(DISTINCT CASE WHEN c.status = 'escalated' THEN c.complaint_id END) as total_escalations
FROM complaints c")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Performance Report | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .sidebar-premium, .btn, .mobile-toggle { display: none !important; }
            .main-content { margin-left: 0 !important; }
            body { background: white !important; color: black !important; }
            .glass-card { background: white !important; border: 1px solid #ccc !important; color: black !important; }
        }
        .performance-card { padding: 1.5rem; }
        .metric-value { font-size: 2rem; font-weight: 700; font-family: var(--font-display); }
        .metric-label { color: var(--primary-400); font-size: 0.9rem; }
        .sla-good { color: #10b981; }
        .sla-warning { color: #f59e0b; }
        .sla-danger { color: #dc2626; }
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
                <li class="nav-item"><a href="staff_performance.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Staff Performance</a></li>
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
            <h1 class="page-title"><i class="fas fa-chart-bar" style="color: var(--accent-cyan);"></i> Staff Performance Summary</h1>
            <p class="page-subtitle">Mandatory Report (R=3) | Generated on <?php echo date('F d, Y \a\t h:i A'); ?></p>
        </div>
        
        <!-- Report Info Banner -->
        <div class="glass-card" style="padding: 1rem; margin-bottom: 2rem; background: rgba(0, 212, 255, 0.1); border-color: var(--accent-cyan);">
            <p style="margin: 0; color: var(--accent-cyan);">
                <i class="fas fa-info-circle"></i> <strong>Report Configuration:</strong> Enrollment 230210107075 | U=75 | Domain: Road/Pathway Surface Damage | Area Model: Ward→Area→Spot
            </p>
        </div>
        
        <!-- Overall Statistics -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="glass-card performance-card">
                <div class="metric-value" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <?php echo $overallStats['total_complaints'] ?? 0; ?>
                </div>
                <div class="metric-label">Total Complaints</div>
            </div>
            <div class="glass-card performance-card">
                <div class="metric-value sla-good">
                    <?php echo $overallStats['total_resolved'] ?? 0; ?>
                </div>
                <div class="metric-label">Total Resolved</div>
            </div>
            <div class="glass-card performance-card">
                <div class="metric-value" style="color: var(--accent-cyan);">
                    <?php echo $overallStats['overall_avg_resolution'] ? round($overallStats['overall_avg_resolution'], 1) : 'N/A'; ?>
                </div>
                <div class="metric-label">Avg Resolution (hrs)</div>
            </div>
            <div class="glass-card performance-card">
                <div class="metric-value sla-danger">
                    <?php echo $overallStats['total_escalations'] ?? 0; ?>
                </div>
                <div class="metric-label">Total Escalations</div>
            </div>
        </div>
        
        <!-- Staff Performance Table -->
        <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-family: var(--font-display); color: white; margin: 0;">
                    <i class="fas fa-users" style="color: var(--accent-cyan);"></i> Individual Staff Performance
                </h3>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            
            <div class="table-container">
                <table class="data-table" id="performanceTable">
                    <thead>
                        <tr>
                            <th>Staff Name</th>
                            <th>Assigned Ward</th>
                            <th>Total Assigned</th>
                            <th>Resolved</th>
                            <th>Resolution Rate</th>
                            <th>Avg Resolution Time</th>
                            <th>SLA Breaches</th>
                            <th>Escalations</th>
                            <th>Avg Feedback Rating</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($staff = $staffPerformance->fetch_assoc()): 
                            $resolutionRate = $staff['total_assigned'] > 0 ? round(($staff['resolved_count'] / $staff['total_assigned']) * 100, 1) : 0;
                            $avgHours = $staff['avg_resolution_hours'] ? round($staff['avg_resolution_hours'], 1) : 0;
                            
                            // Performance rating
                            if($resolutionRate >= 90 && $staff['sla_breach_count'] == 0) {
                                $performance = 'Excellent';
                                $perfColor = '#10b981';
                            } elseif($resolutionRate >= 70 && $staff['sla_breach_count'] <= 2) {
                                $performance = 'Good';
                                $perfColor = '#3b82f6';
                            } elseif($resolutionRate >= 50) {
                                $performance = 'Average';
                                $perfColor = '#f59e0b';
                            } else {
                                $performance = 'Needs Improvement';
                                $perfColor = '#dc2626';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($staff['staff_name']); ?></strong><br>
                                <small style="color: var(--primary-400);"><?php echo htmlspecialchars($staff['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($staff['assigned_ward'] ?: 'Not Assigned'); ?></td>
                            <td style="text-align: center; font-weight: 600;"><?php echo $staff['total_assigned']; ?></td>
                            <td style="text-align: center; color: #10b981; font-weight: 600;"><?php echo $staff['resolved_count']; ?></td>
                            <td style="text-align: center;">
                                <div style="background: rgba(255,255,255,0.1); border-radius: 10px; height: 20px; position: relative;">
                                    <div style="background: linear-gradient(90deg, #10b981, #3b82f6); width: <?php echo $resolutionRate; ?>%; height: 100%; border-radius: 10px;"></div>
                                    <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 0.8rem; font-weight: 600;"><?php echo $resolutionRate; ?>%</span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <?php if($avgHours > 0): ?>
                                    <span class="<?php echo $avgHours <= RESOLUTION_SLA_HOURS ? 'sla-good' : 'sla-warning'; ?>">
                                        <?php echo $avgHours; ?> hrs
                                    </span>
                                <?php else: ?>
                                    <em style="color: var(--primary-400);">N/A</em>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="<?php echo $staff['sla_breach_count'] > 0 ? 'sla-danger' : 'sla-good'; ?>" style="font-weight: 600;">
                                    <?php echo $staff['sla_breach_count']; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="<?php echo $staff['escalation_count'] > 0 ? 'sla-warning' : 'sla-good'; ?>" style="font-weight: 600;">
                                    <?php echo $staff['escalation_count']; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if($staff['avg_feedback_rating']): ?>
                                    <span style="color: var(--accent-yellow); font-size: 1.1rem;">
                                        <?php echo str_repeat('★', round($staff['avg_feedback_rating'])); ?>
                                    </span>
                                    <br>
                                    <small style="color: var(--primary-400);">
                                        <?php echo round($staff['avg_feedback_rating'], 1); ?> / 5 
                                        (<?php echo $staff['feedback_count']; ?> reviews)
                                    </small>
                                <?php else: ?>
                                    <em style="color: var(--primary-500);">No feedback yet</em>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <span style="background: <?php echo $perfColor; ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;">
                                    <?php echo $performance; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Chart Section -->
        <div class="glass-card" style="padding: 1.5rem;">
            <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                <i class="fas fa-chart-pie" style="color: var(--accent-purple);"></i> Performance Visualization
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
                <div>
                    <canvas id="resolutionChart" height="250"></canvas>
                </div>
                <div>
                    <canvas id="slaChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Legend / Notes -->
        <div class="glass-card" style="padding: 1.5rem; margin-top: 2rem;">
            <h4 style="color: var(--accent-cyan); margin-bottom: 1rem;"><i class="fas fa-clipboard"></i> Report Notes</h4>
            <ul style="color: var(--primary-300); line-height: 1.8;">
                <li><strong>Resolution Rate:</strong> Percentage of assigned complaints that have been resolved</li>
                <li><strong>Avg Resolution Time:</strong> Average hours taken from assignment to resolution</li>
                <li><strong>SLA Breaches:</strong> Complaints that crossed the <?php echo RESOLUTION_SLA_HOURS; ?>-hour resolution deadline</li>
                <li><strong>Escalations:</strong> Complaints that were escalated due to SLA breaches</li>
                <li><strong>Performance Rating:</strong> Excellent (90%+ resolution, 0 breaches), Good (70%+ resolution, ≤2 breaches), Average (50%+ resolution), Needs Improvement (&lt;50% resolution)</li>
            </ul>
        </div>
    </main>
    
    <script>
    // Prepare data for charts
    const staffData = <?php 
        $staffPerformance->data_seek(0);
        $chartData = [];
        while($s = $staffPerformance->fetch_assoc()) {
            $chartData[] = [
                'name' => $s['staff_name'],
                'resolved' => (int)$s['resolved_count'],
                'assigned' => (int)$s['total_assigned'] - (int)$s['resolved_count'],
                'breaches' => (int)$s['sla_breach_count'],
                'escalations' => (int)$s['escalation_count']
            ];
        }
        echo json_encode($chartData);
    ?>;
    
    // Resolution Chart
    new Chart(document.getElementById('resolutionChart'), {
        type: 'bar',
        data: {
            labels: staffData.map(s => s.name.split(' ')[0]),
            datasets: [
                {
                    label: 'Resolved',
                    data: staffData.map(s => s.resolved),
                    backgroundColor: '#10b981'
                },
                {
                    label: 'Pending',
                    data: staffData.map(s => s.assigned),
                    backgroundColor: '#f59e0b'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Complaint Resolution by Staff',
                    color: 'white'
                },
                legend: {
                    labels: { color: 'white' }
                }
            },
            scales: {
                x: { ticks: { color: 'white' } },
                y: { ticks: { color: 'white' } }
            }
        }
    });
    
    // SLA Breach Chart
    new Chart(document.getElementById('slaChart'), {
        type: 'doughnut',
        data: {
            labels: staffData.map(s => s.name.split(' ')[0]),
            datasets: [{
                data: staffData.map(s => s.breaches),
                backgroundColor: ['#ff416c', '#ff4b2b', '#f59e0b', '#8b5cf6', '#06b6d4']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'SLA Breaches by Staff',
                    color: 'white'
                },
                legend: {
                    labels: { color: 'white' }
                }
            }
        }
    });
    </script>
</body>
</html>
