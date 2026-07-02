<?php
/**
 * Complaint Detail View with Timeline
 * Shows full complaint history and status changes
 */
session_start();
require_once("config.php");

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$complaintId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch complaint details with access check
if($role === 'supervisor') {
    $stmt = $conn->prepare("SELECT c.*, cat.category_name,
        w.area_name as ward_name, a.area_name as area_name, s.area_name as spot_name,
        comp.name as complainant_name, comp.email as complainant_email, comp.phone as complainant_phone,
        st.name as staff_name
        FROM complaints c
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        LEFT JOIN area_master w ON c.ward_id = w.area_id
        LEFT JOIN area_master a ON c.area_id = a.area_id
        LEFT JOIN area_master s ON c.spot_id = s.area_id
        LEFT JOIN users comp ON c.complainant_id = comp.user_id
        LEFT JOIN users st ON c.assigned_to = st.user_id
        WHERE c.complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
} elseif($role === 'staff') {
    $stmt = $conn->prepare("SELECT c.*, cat.category_name,
        w.area_name as ward_name, a.area_name as area_name, s.area_name as spot_name,
        comp.name as complainant_name, comp.email as complainant_email, comp.phone as complainant_phone,
        st.name as staff_name
        FROM complaints c
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        LEFT JOIN area_master w ON c.ward_id = w.area_id
        LEFT JOIN area_master a ON c.area_id = a.area_id
        LEFT JOIN area_master s ON c.spot_id = s.area_id
        LEFT JOIN users comp ON c.complainant_id = comp.user_id
        LEFT JOIN users st ON c.assigned_to = st.user_id
        WHERE c.complaint_id = ? AND (c.assigned_to = ? OR c.assigned_to IS NULL)");
    $stmt->bind_param("ii", $complaintId, $userId);
} else {
    $stmt = $conn->prepare("SELECT c.*, cat.category_name,
        w.area_name as ward_name, a.area_name as area_name, s.area_name as spot_name,
        comp.name as complainant_name, comp.email as complainant_email, comp.phone as complainant_phone,
        st.name as staff_name
        FROM complaints c
        LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
        LEFT JOIN area_master w ON c.ward_id = w.area_id
        LEFT JOIN area_master a ON c.area_id = a.area_id
        LEFT JOIN area_master s ON c.spot_id = s.area_id
        LEFT JOIN users comp ON c.complainant_id = comp.user_id
        LEFT JOIN users st ON c.assigned_to = st.user_id
        WHERE c.complaint_id = ? AND c.complainant_id = ?");
    $stmt->bind_param("ii", $complaintId, $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$complaint = $result->fetch_assoc();
$stmt->close();

if(!$complaint) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Fetch complaint history
$stmt = $conn->prepare("SELECT ch.*, u.name as updater_name, u.role as updater_role
    FROM complaint_history ch
    LEFT JOIN users u ON ch.updated_by = u.user_id
    WHERE ch.complaint_id = ?
    ORDER BY ch.updated_at ASC");
$stmt->bind_param("i", $complaintId);
$stmt->execute();
$history = $stmt->get_result();
$stmt->close();

// Fetch attachments
$stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $complaintId);
$stmt->execute();
$attachments = $stmt->get_result();
$stmt->close();

// Check if feedback already submitted
$feedback = null;
$stmt = $conn->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
$stmt->bind_param("i", $complaintId);
$stmt->execute();
$feedbackResult = $stmt->get_result();
if($feedbackResult->num_rows > 0) {
    $feedback = $feedbackResult->fetch_assoc();
}
$stmt->close();

// Handle feedback submission
$feedbackMessage = '';
$feedbackError = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Check if user is the complainant and complaint is resolved/closed
    if($role === 'complainant' && $complaint['complainant_id'] == $userId && 
       in_array($complaint['status'], ['resolved', 'closed']) && !$feedback) {
        
        $rating = intval($_POST['rating']);
        $remarks = trim($_POST['remarks'] ?? '');
        
        // Server-side validation
        if($rating < 1 || $rating > 5) {
            $feedbackError = 'Rating must be between 1 and 5 stars.';
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback (complaint_id, complainant_id, rating, remarks, submitted_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiis", $complaintId, $userId, $rating, $remarks);
            if($stmt->execute()) {
                $feedbackMessage = 'Thank you for your feedback! Your rating has been submitted.';
                // Refresh feedback data
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
                $stmt->bind_param("i", $complaintId);
                $stmt->execute();
                $feedbackResult = $stmt->get_result();
                $feedback = $feedbackResult->fetch_assoc();
            } else {
                $feedbackError = 'Error submitting feedback. Please try again.';
            }
            $stmt->close();
        }
    }
}

// SLA Status
$slaStatus = getSLAStatus($complaint['resolution_sla_deadline'], $complaint['initial_sla_deadline'], $complaint['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint #<?php echo htmlspecialchars($complaint['complaint_code']); ?> | Complaint Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--glass-border);
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-cyan);
            border: 2px solid var(--primary-900);
        }
        .timeline-item.status-submitted::before { background: var(--accent-yellow); }
        .timeline-item.status-verified::before { background: #3b82f6; }
        .timeline-item.status-assigned::before { background: var(--accent-purple); }
        .timeline-item.status-in_progress::before { background: var(--accent-cyan); }
        .timeline-item.status-resolved::before { background: var(--accent-green); }
        .timeline-item.status-escalated::before { background: #dc2626; }
        .timeline-time {
            font-size: 0.85rem;
            color: var(--primary-400);
        }
        .timeline-content {
            background: rgba(255,255,255,0.03);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 0.5rem;
        }
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        .image-thumb {
            border-radius: var(--radius-md);
            overflow: hidden;
            aspect-ratio: 4/3;
        }
        .image-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            <div class="sidebar-logo"><i class="fas fa-shield-alt"></i></div>
            <h2 class="sidebar-title">Complaint Portal</h2>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a></li>
                <?php if($role === 'supervisor'): ?>
                <li class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-cogs"></i> Admin Panel</a></li>
                <?php elseif($role === 'staff'): ?>
                <li class="nav-item"><a href="staff.php" class="nav-link"><i class="fas fa-tasks"></i> My Work</a></li>
                <?php endif; ?>
                <li class="divider"></li>
                <li class="nav-item"><a href="logout.php" class="nav-link" style="color: #ff416c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 class="page-title"><?php echo htmlspecialchars($complaint['title']); ?></h1>
                    <p class="page-subtitle">
                        <span style="font-family: var(--font-mono); color: var(--accent-cyan);"><?php echo htmlspecialchars($complaint['complaint_code']); ?></span>
                        <span style="margin: 0 0.5rem;">|</span>
                        Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($complaint['submitted_at'])); ?>
                    </p>
                </div>
                <div>
                    <?php echo getStatusBadge($complaint['status']); ?>
                    <?php if($complaint['is_repeated']): ?>
                    <span style="background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; margin-left: 0.5rem;">
                        <i class="fas fa-clone"></i> REPEATED
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Left Column -->
            <div>
                <!-- Complaint Details -->
                <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                        <i class="fas fa-info-circle" style="color: var(--accent-cyan);"></i> Complaint Details
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <label style="color: var(--primary-400); font-size: 0.85rem;">Category</label>
                            <p style="color: white; margin: 0;"><i class="fas fa-tags" style="color: var(--accent-orange);"></i> <?php echo htmlspecialchars($complaint['category_name']); ?></p>
                        </div>
                        <div>
                            <label style="color: var(--primary-400); font-size: 0.85rem;">Priority</label>
                            <p style="color: white; margin: 0;">
                                <span style="text-transform: uppercase; padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; background: <?php 
                                    echo $complaint['priority'] === 'critical' ? '#dc2626' : 
                                        ($complaint['priority'] === 'high' ? '#f59e0b' : 
                                        ($complaint['priority'] === 'medium' ? '#3b82f6' : '#10b981')); 
                                ?>;"><?php echo ucfirst($complaint['priority']); ?></span>
                            </p>
                        </div>
                        <div>
                            <label style="color: var(--primary-400); font-size: 0.85rem;">Location</label>
                            <p style="color: white; margin: 0;">
                                <i class="fas fa-map-marker-alt" style="color: var(--accent-pink);"></i>
                                <?php echo htmlspecialchars($complaint['ward_name'] . ' → ' . $complaint['area_name'] . ' → ' . $complaint['spot_name']); ?>
                            </p>
                        </div>
                        <div>
                            <label style="color: var(--primary-400); font-size: 0.85rem;">Exact Location</label>
                            <p style="color: white; margin: 0;"><?php echo htmlspecialchars($complaint['exact_location'] ?: 'N/A'); ?></p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <label style="color: var(--primary-400); font-size: 0.85rem;">Description</label>
                        <p style="color: var(--primary-200); margin: 0.5rem 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="glass-card" style="padding: 1.5rem;">
                    <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1.5rem;">
                        <i class="fas fa-history" style="color: var(--accent-purple);"></i> Complaint Timeline
                    </h3>
                    <div class="timeline">
                        <div class="timeline-item status-submitted">
                            <div class="timeline-time">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($complaint['submitted_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <strong style="color: white;"><i class="fas fa-paper-plane" style="color: var(--accent-yellow);"></i> Complaint Submitted</strong>
                                <p style="color: var(--primary-400); margin: 0.25rem 0; font-size: 0.9rem;">by <?php echo htmlspecialchars($complaint['complainant_name']); ?></p>
                            </div>
                        </div>
                        
                        <?php while($h = $history->fetch_assoc()): 
                            $statusClass = 'status-' . str_replace('_', '-', $h['new_status']);
                        ?>
                        <div class="timeline-item <?php echo $statusClass; ?>">
                            <div class="timeline-time">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($h['updated_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <strong style="color: white;">
                                    <i class="fas fa-exchange-alt" style="color: var(--accent-cyan);"></i> 
                                    Status Changed: <?php echo $h['old_status'] ? ucwords(str_replace('_', ' ', $h['old_status'])) : 'New'; ?> 
                                    <i class="fas fa-arrow-right" style="margin: 0 0.5rem;"></i> 
                                    <?php echo ucwords(str_replace('_', ' ', $h['new_status'])); ?>
                                </strong>
                                <p style="color: var(--primary-400); margin: 0.25rem 0; font-size: 0.9rem;">
                                    by <?php echo htmlspecialchars($h['updater_name']); ?> (<?php echo ucfirst($h['updater_role']); ?>)
                                </p>
                                <?php if($h['remarks']): ?>
                                <p style="color: var(--primary-300); margin: 0.5rem 0 0; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 4px; font-size: 0.9rem;">
                                    <i class="fas fa-comment"></i> <?php echo htmlspecialchars($h['remarks']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <!-- Feedback Section -->
                <?php if($role === 'complainant' && $complaint['complainant_id'] == $userId && in_array($complaint['status'], ['resolved', 'closed'])): ?>
                    <?php if($feedback): ?>
                    <!-- Feedback Already Submitted -->
                    <div class="glass-card" style="padding: 1.5rem; margin-top: 1.5rem; border-left: 4px solid var(--accent-green);">
                        <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                            <i class="fas fa-check-circle" style="color: var(--accent-green);"></i> Feedback Submitted
                        </h3>
                        <div style="text-align: center; padding: 1.5rem;">
                            <div style="font-size: 2rem; color: var(--accent-yellow); margin-bottom: 0.5rem;">
                                <?php echo str_repeat('★', $feedback['rating']) . str_repeat('☆', 5 - $feedback['rating']); ?>
                            </div>
                            <p style="color: white; font-size: 1.25rem; margin: 0;">
                                <strong><?php echo $feedback['rating']; ?> / 5</strong>
                            </p>
                            <?php if($feedback['remarks']): ?>
                            <p style="color: var(--primary-300); margin-top: 1rem; font-style: italic;">
                                <i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($feedback['remarks']); ?> <i class="fas fa-quote-right"></i>
                            </p>
                            <?php endif; ?>
                            <p style="color: var(--primary-400); font-size: 0.85rem; margin-top: 1rem;">
                                Submitted on <?php echo date('F d, Y', strtotime($feedback['submitted_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Submit Feedback Form -->
                    <div class="glass-card" style="padding: 1.5rem; margin-top: 1.5rem; border-left: 4px solid var(--accent-cyan);">
                        <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                            <i class="fas fa-star" style="color: var(--accent-yellow);"></i> Submit Feedback
                        </h3>
                        <p style="color: var(--primary-400); margin-bottom: 1.5rem;">
                            Your complaint has been resolved. Please rate your experience with our service.
                        </p>
                        
                        <?php if($feedbackMessage): ?>
                        <div class="alert alert-success" style="margin-bottom: 1rem;">
                            <i class="fas fa-check-circle"></i> <?php echo $feedbackMessage; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($feedbackError): ?>
                        <div class="alert alert-error" style="margin-bottom: 1rem;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $feedbackError; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group" style="text-align: center;">
                                <label class="form-label" style="display: block; margin-bottom: 1rem;">Rate Your Experience</label>
                                <div class="star-rating" style="font-size: 2.5rem; cursor: pointer; user-select: none;">
                                    <span class="star" data-rating="1" style="color: var(--primary-600); transition: color 0.2s;">★</span>
                                    <span class="star" data-rating="2" style="color: var(--primary-600); transition: color 0.2s;">★</span>
                                    <span class="star" data-rating="3" style="color: var(--primary-600); transition: color 0.2s;">★</span>
                                    <span class="star" data-rating="4" style="color: var(--primary-600); transition: color 0.2s;">★</span>
                                    <span class="star" data-rating="5" style="color: var(--primary-600); transition: color 0.2s;">★</span>
                                </div>
                                <input type="hidden" name="rating" id="rating-value" value="0" required>
                                <p id="rating-text" style="color: var(--primary-400); margin-top: 0.5rem; font-size: 0.9rem;">Click a star to rate</p>
                            </div>
                            
                            <div class="form-group" style="margin-top: 1.5rem;">
                                <label class="form-label">Additional Remarks (Optional)</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Share your experience or suggestions..."></textarea>
                            </div>
                            
                            <div style="margin-top: 1.5rem; text-align: center;">
                                <button type="submit" name="submit_feedback" class="btn btn-primary" style="min-width: 200px;">
                                    <i class="fas fa-paper-plane"></i> Submit Feedback
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <script>
                    // Star rating interaction
                    const stars = document.querySelectorAll('.star');
                    const ratingInput = document.getElementById('rating-value');
                    const ratingText = document.getElementById('rating-text');
                    const ratingLabels = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                    
                    stars.forEach((star, index) => {
                        star.addEventListener('click', () => {
                            const rating = index + 1;
                            ratingInput.value = rating;
                            updateStars(rating);
                            ratingText.textContent = ratingLabels[index];
                            ratingText.style.color = 'var(--accent-cyan)';
                        });
                        
                        star.addEventListener('mouseenter', () => {
                            const rating = index + 1;
                            highlightStars(rating);
                        });
                    });
                    
                    document.querySelector('.star-rating').addEventListener('mouseleave', () => {
                        const currentRating = parseInt(ratingInput.value) || 0;
                        updateStars(currentRating);
                    });
                    
                    function highlightStars(count) {
                        stars.forEach((star, index) => {
                            if (index < count) {
                                star.style.color = 'var(--accent-yellow)';
                            } else {
                                star.style.color = 'var(--primary-600)';
                            }
                        });
                    }
                    
                    function updateStars(count) {
                        stars.forEach((star, index) => {
                            if (index < count) {
                                star.style.color = 'var(--accent-yellow)';
                            } else {
                                star.style.color = 'var(--primary-600)';
                            }
                        });
                    }
                    </script>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- SLA Status -->
                <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                        <i class="fas fa-stopwatch" style="color: var(--accent-cyan);"></i> SLA Status
                    </h3>
                    <?php if($slaStatus === 'completed'): ?>
                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                        <p style="color: #10b981; margin: 0; font-weight: 600;">COMPLAINT RESOLVED</p>
                        <p style="color: var(--primary-400); margin: 0.5rem 0; font-size: 0.9rem;">Completed within SLA</p>
                    </div>
                    <?php elseif($slaStatus === 'escalated'): ?>
                    <div style="background: rgba(220, 38, 38, 0.1); border: 2px solid #dc2626; border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc2626; margin-bottom: 0.5rem;"></i>
                        <p style="color: #dc2626; margin: 0; font-weight: 600;">ESCALATED</p>
                        <p style="color: var(--primary-400); margin: 0.5rem 0; font-size: 0.9rem;">Resolution SLA breached</p>
                    </div>
                    <?php elseif($slaStatus === 'initial_breach'): ?>
                    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <i class="fas fa-clock" style="font-size: 3rem; color: #f59e0b; margin-bottom: 0.5rem;"></i>
                        <p style="color: #f59e0b; margin: 0; font-weight: 600;">INITIAL SLA BREACHED</p>
                        <p style="color: var(--primary-400); margin: 0.5rem 0; font-size: 0.9rem;">Initial response was delayed</p>
                    </div>
                    <?php else: ?>
                    <div style="background: rgba(0, 212, 255, 0.1); border: 1px solid var(--accent-cyan); border-radius: var(--radius-md); padding: 1rem; text-align: center;">
                        <i class="fas fa-clock" style="font-size: 3rem; color: var(--accent-cyan); margin-bottom: 0.5rem;"></i>
                        <p style="color: var(--accent-cyan); margin: 0; font-weight: 600;">WITHIN SLA</p>
                        <p style="color: var(--primary-400); margin: 0.5rem 0; font-size: 0.9rem;">On track for resolution</p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 1rem;">
                        <p style="color: var(--primary-400); font-size: 0.85rem; margin: 0;">
                            <i class="fas fa-hourglass-start"></i> Initial SLA (<?php echo INITIAL_SLA_HOURS; ?> hrs):<br>
                            <span style="color: white;"><?php echo date('M d, Y h:i A', strtotime($complaint['initial_sla_deadline'])); ?></span>
                        </p>
                        <p style="color: var(--primary-400); font-size: 0.85rem; margin: 0.5rem 0 0;">
                            <i class="fas fa-hourglass-end"></i> Resolution SLA (<?php echo RESOLUTION_SLA_HOURS; ?> hrs):<br>
                            <span style="color: white;"><?php echo date('M d, Y h:i A', strtotime($complaint['resolution_sla_deadline'])); ?></span>
                        </p>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="glass-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                        <i class="fas fa-address-card" style="color: var(--accent-green);"></i> Contact Information
                    </h3>
                    <p style="color: var(--primary-300); margin: 0.5rem 0;">
                        <i class="fas fa-user" style="color: var(--accent-cyan); width: 20px;"></i>
                        <strong><?php echo htmlspecialchars($complaint['complainant_name']); ?></strong>
                    </p>
                    <p style="color: var(--primary-300); margin: 0.5rem 0;">
                        <i class="fas fa-envelope" style="color: var(--accent-cyan); width: 20px;"></i>
                        <?php echo htmlspecialchars($complaint['complainant_email']); ?>
                    </p>
                    <p style="color: var(--primary-300); margin: 0.5rem 0;">
                        <i class="fas fa-phone" style="color: var(--accent-cyan); width: 20px;"></i>
                        <?php echo htmlspecialchars($complaint['complainant_phone'] ?: 'N/A'); ?>
                    </p>
                    
                    <?php if($complaint['staff_name']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                        <p style="color: var(--primary-400); font-size: 0.85rem; margin: 0;">Assigned Staff:</p>
                        <p style="color: white; margin: 0.25rem 0;">
                            <i class="fas fa-hard-hat" style="color: var(--accent-purple);"></i>
                            <?php echo htmlspecialchars($complaint['staff_name']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Attachments -->
                <?php if($attachments->num_rows > 0): ?>
                <div class="glass-card" style="padding: 1.5rem;">
                    <h3 style="font-family: var(--font-display); color: white; margin-bottom: 1rem;">
                        <i class="fas fa-paperclip" style="color: var(--accent-yellow);"></i> Attachments
                    </h3>
                    <div class="image-grid">
                        <?php while($att = $attachments->fetch_assoc()): 
                            if(strpos($att['mime_type'], 'image') !== false):
                        ?>
                        <a href="<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" class="image-thumb">
                            <img src="<?php echo htmlspecialchars($att['file_path']); ?>" alt="Attachment">
                        </a>
                        <?php else: ?>
                        <a href="<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" style="background: rgba(255,255,255,0.05); border-radius: var(--radius-md); padding: 1rem; text-align: center; text-decoration: none; color: white;">
                            <i class="fas fa-file-pdf" style="font-size: 2rem; color: var(--accent-orange); margin-bottom: 0.5rem;"></i>
                            <p style="margin: 0; font-size: 0.85rem;"><?php echo htmlspecialchars($att['original_name']); ?></p>
                            <small style="color: var(--primary-400);"><?php echo ucfirst(str_replace('_', ' ', $att['file_type'])); ?></small>
                        </a>
                        <?php endif; endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <a href="<?php echo $role === 'supervisor' ? 'admin.php' : ($role === 'staff' ? 'staff.php' : 'dashboard.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </main>
</body>
</html>
