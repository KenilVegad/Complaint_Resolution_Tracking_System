<?php
session_start();
require_once("config.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$success = '';
$error = '';
$isRepeated = false;
$parentComplaintId = null;

// Fetch wards for dropdown
$wards = $conn->query("SELECT area_id, area_name FROM area_master WHERE area_type = 'ward' AND is_active = 1 ORDER BY area_name");

// Fetch categories for dropdown
$categories = $conn->query("SELECT category_id, category_name FROM complaint_categories WHERE is_active = 1 ORDER BY category_name");

// Process form submission
if(isset($_POST['submit'])){
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $ward_id = intval($_POST['ward_id']);
    $area_id = intval($_POST['area_id']);
    $spot_id = intval($_POST['spot_id']);
    $exact_location = sanitize($_POST['exact_location']);
    $priority = sanitize($_POST['priority']);
    $complainant_id = $_SESSION['user_id'];
    
    // Validation
    if(empty($title) || empty($description) || empty($category_id) || empty($ward_id) || empty($area_id) || empty($spot_id)) {
        $error = "All required fields must be filled";
    } elseif(strlen($title) < 10) {
        $error = "Title must be at least 10 characters long";
    } elseif(strlen($description) < 20) {
        $error = "Description must be at least 20 characters long";
    } else {
        // Generate complaint code
        $complaintCode = generateComplaintCode();
        
        // Calculate SLA deadlines
        $now = date('Y-m-d H:i:s');
        $slaDeadlines = calculateSLADeadlines($now);
        
        // Check for repeated complaint (Special Rule: U is Odd)
        $existing = checkRepeatedComplaint($ward_id, $area_id, $spot_id, $category_id);
        if($existing) {
            $isRepeated = true;
            $parentComplaintId = $existing['complaint_id'];
        }
        
        // Handle file upload
        $uploadResult = ['success' => true, 'path' => null];
        if(isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = validateFileUpload($_FILES['image']);
        }
        
        if(!$uploadResult['success']) {
            $error = $uploadResult['message'];
        } else {
            // Insert complaint
            $stmt = $conn->prepare("INSERT INTO complaints 
                (complaint_code, complainant_id, category_id, ward_id, area_id, spot_id, exact_location, 
                 title, description, priority, status, is_repeated, repeated_parent_id,
                 initial_sla_deadline, resolution_sla_deadline, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("siiiiissssissis", 
                $complaintCode, $complainant_id, $category_id, $ward_id, $area_id, $spot_id, 
                $exact_location, $title, $description, $priority, $isRepeated, $parentComplaintId,
                $slaDeadlines['initial_sla'], $slaDeadlines['resolution_sla'], $now
            );
            
            if($stmt->execute()) {
                $newComplaintId = $stmt->insert_id;
                $stmt->close();
                
                // Log initial status
                logComplaintHistory($newComplaintId, null, 'submitted', $complainant_id, 'Complaint submitted by user');
                
                // Save attachment if uploaded
                if($uploadResult['path']) {
                    $stmt = $conn->prepare("INSERT INTO complaint_attachments 
                        (complaint_id, file_path, file_type, original_name, file_size, mime_type, uploaded_by) 
                        VALUES (?, ?, 'complaint_proof', ?, ?, ?, ?)");
                    $stmt->bind_param("issisi", $newComplaintId, $uploadResult['path'], 
                        $uploadResult['original_name'], $uploadResult['file_size'], 
                        $uploadResult['mime_type'], $complainant_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Save filter cookie for ward (bonus feature)
                setFilterCookie('last_ward', $ward_id);
                
                $uploadMsg = $uploadResult['path'] ? ' (with image attachment)' : '';
                if($isRepeated) {
                    $success = "Complaint submitted successfully{$uploadMsg}! Note: This has been flagged as a repeated complaint (similar to #{$existing['complaint_code']}). Your Complaint ID: {$complaintCode}";
                } else {
                    $success = "Complaint submitted successfully{$uploadMsg}! Your Complaint ID: {$complaintCode}";
                }
            } else {
                $error = "Error submitting complaint. Please try again.";
            }
        }
    }
}

$role = $_SESSION['role'];

// Get saved ward from cookie for pre-selection
$savedWard = getFilterCookie('last_ward');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Complaint | Citizen Complaint Portal</title>
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
                    <a href="complaint.php" class="nav-link active">
                        <i class="fas fa-plus-circle"></i> Register Complaint
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view_complaints.php" class="nav-link">
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
            <h1 class="page-title">Register Complaint</h1>
            <p class="page-subtitle">Report civic issues in your community</p>
        </div>
        
        <?php if($success): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
            <a href="view_complaints.php" style="color: #00e676; margin-left: 1rem; font-weight: 600;">View Complaints →</a>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($error)): ?>
        <div class="alert alert-error fade-in" style="background: rgba(220, 38, 38, 0.2); border: 1px solid rgba(220, 38, 38, 0.5); color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- Duplicate Warning Banner -->
        <div id="duplicateWarning" class="alert alert-warning fade-in" style="display: none; margin-bottom: 1rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <span id="duplicateMessage"></span>
        </div>
        
        <div class="glass-card" style="padding: 2rem; max-width: 900px;">
            <form method="POST" enctype="multipart/form-data" id="complaintForm">
                
                <!-- Complaint Details Section -->
                <h3 style="color: var(--accent-cyan); margin-bottom: 1.5rem; font-family: var(--font-display);">
                    <i class="fas fa-info-circle"></i> Complaint Details
                </h3>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-heading" style="color: var(--accent-cyan);"></i> Complaint Title *
                    </label>
                    <input type="text" name="title" id="title" class="form-control" placeholder="Enter a brief title (min 10 characters)" minlength="10" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-align-left" style="color: var(--accent-purple);"></i> Description *
                    </label>
                    <textarea name="description" id="description" class="form-control" placeholder="Describe the issue in detail (min 20 characters)..." minlength="20" rows="4" required></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tags" style="color: var(--accent-orange);"></i> Category *
                        </label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select a category</option>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-exclamation-circle" style="color: var(--accent-pink);"></i> Priority *
                        </label>
                        <select name="priority" id="priority" class="form-control" required>
                            <option value="low">Low - Minor issue</option>
                            <option value="medium" selected>Medium - Moderate concern</option>
                            <option value="high">High - Significant problem</option>
                            <option value="critical">Critical - Safety hazard</option>
                        </select>
                    </div>
                </div>
                
                <!-- Location Section -->
                <h3 style="color: var(--accent-green); margin: 2rem 0 1.5rem; font-family: var(--font-display);">
                    <i class="fas fa-map-marked-alt"></i> Location Details (Ward → Area → Spot)
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt" style="color: var(--accent-pink);"></i> Ward *
                        </label>
                        <select name="ward_id" id="ward_id" class="form-control" required>
                            <option value="">Select Ward</option>
                            <?php 
                            $wards->data_seek(0);
                            while($ward = $wards->fetch_assoc()): 
                                $selected = ($savedWard == $ward['area_id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $ward['area_id']; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($ward['area_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-city" style="color: var(--accent-cyan);"></i> Area *
                        </label>
                        <select name="area_id" id="area_id" class="form-control" required disabled>
                            <option value="">First select a Ward</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-location-arrow" style="color: var(--accent-green);"></i> Specific Spot *
                        </label>
                        <select name="spot_id" id="spot_id" class="form-control" required disabled>
                            <option value="">First select an Area</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-street-view" style="color: var(--accent-yellow);"></i> Exact Location Description
                    </label>
                    <input type="text" name="exact_location" class="form-control" placeholder="e.g., Near Shiv Temple, Opposite Bank of Baroda">
                </div>
                
                <!-- File Upload Section -->
                <h3 style="color: var(--accent-yellow); margin: 2rem 0 1.5rem; font-family: var(--font-display);">
                    <i class="fas fa-camera"></i> Evidence Upload (Optional)
                </h3>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-image" style="color: var(--accent-yellow);"></i> Upload Image/Document
                    </label>
                    <div class="file-upload">
                        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/jpg,application/pdf" onchange="previewFile(this)">
                        <label for="image" class="file-upload-label" id="fileLabel">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload or drag and drop<br><small>JPG, PNG, PDF (Max 5MB)</small></span>
                        </label>
                    </div>
                    <div id="filePreview" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
                        <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                        <span id="fileName" style="color: var(--primary-200);"></span>
                        <span id="fileSize" style="color: var(--primary-400); font-size: 0.85rem; margin-left: 0.5rem;"></span>
                    </div>
                    <small style="color: var(--primary-400);">File upload is optional but helps in faster resolution</small>
                    
                    <script>
                    function previewFile(input) {
                        var preview = document.getElementById('filePreview');
                        var fileName = document.getElementById('fileName');
                        var fileSize = document.getElementById('fileSize');
                        var label = document.getElementById('fileLabel');
                        
                        if (input.files && input.files[0]) {
                            var file = input.files[0];
                            var sizeMB = (file.size / 1024 / 1024).toFixed(2);
                            
                            fileName.textContent = file.name;
                            fileSize.textContent = '(' + sizeMB + ' MB)';
                            preview.style.display = 'block';
                            label.innerHTML = '<i class="fas fa-check"></i><span>File selected<br><small>Click to change file</small></span>';
                        } else {
                            preview.style.display = 'none';
                            label.innerHTML = '<i class="fas fa-cloud-upload-alt"></i><span>Click to upload or drag and drop<br><small>JPG, PNG, PDF (Max 5MB)</small></span>';
                        }
                    }
                    </script>
                </div>
                
                <!-- SLA Info -->
                <div style="background: rgba(0, 212, 255, 0.1); border: 1px solid rgba(0, 212, 255, 0.3); border-radius: var(--radius-md); padding: 1rem; margin: 1.5rem 0;">
                    <p style="color: var(--accent-cyan); margin: 0;">
                        <i class="fas fa-clock"></i> <strong>SLA Commitment:</strong> Initial response within <?php echo INITIAL_SLA_HOURS; ?> hours, Resolution within <?php echo RESOLUTION_SLA_HOURS; ?> hours
                    </p>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" name="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Submit Complaint
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
        
        <!-- AJAX Scripts for Dependent Dropdowns -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
        $(document).ready(function() {
            // Ward change - Load Areas
            $('#ward_id').change(function() {
                var wardId = $(this).val();
                if(wardId) {
                    $.ajax({
                        url: 'api/get_areas.php',
                        type: 'GET',
                        data: {ward_id: wardId},
                        dataType: 'json',
                        success: function(data) {
                            var options = '<option value="">Select Area</option>';
                            if(data && data.length > 0) {
                                $.each(data, function(index, area) {
                                    options += '<option value="' + area.area_id + '">' + area.area_name + '</option>';
                                });
                                $('#area_id').html(options).prop('disabled', false);
                            } else {
                                options += '<option value="" disabled>⚠️ No areas found. Please create areas in Area Master first.</option>';
                                $('#area_id').html(options).prop('disabled', true);
                            }
                            $('#spot_id').html('<option value="">First select an Area</option>').prop('disabled', true);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading areas:', error);
                            alert('Error loading areas. Please refresh the page and try again.');
                        }
                    });
                } else {
                    $('#area_id').html('<option value="">First select a Ward</option>').prop('disabled', true);
                    $('#spot_id').html('<option value="">First select an Area</option>').prop('disabled', true);
                }
            });
            
            // Area change - Load Spots
            $('#area_id').change(function() {
                var areaId = $(this).val();
                if(areaId) {
                    $.ajax({
                        url: 'api/get_spots.php',
                        type: 'GET',
                        data: {area_id: areaId},
                        dataType: 'json',
                        success: function(data) {
                            var options = '<option value="">Select Spot</option>';
                            if(data && data.length > 0) {
                                $.each(data, function(index, spot) {
                                    options += '<option value="' + spot.area_id + '">' + spot.area_name + '</option>';
                                });
                                $('#spot_id').html(options).prop('disabled', false);
                            } else {
                                options += '<option value="" disabled>No spots available for this area</option>';
                                $('#spot_id').html(options).prop('disabled', true);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading spots:', error);
                            alert('Error loading spots. Please refresh the page and try again.');
                        }
                    });
                } else {
                    $('#spot_id').html('<option value="">First select an Area</option>').prop('disabled', true);
                }
            });
            
            // Check for duplicate complaint when all location fields are selected
            function checkDuplicate() {
                var wardId = $('#ward_id').val();
                var areaId = $('#area_id').val();
                var spotId = $('#spot_id').val();
                var categoryId = $('#category_id').val();
                
                if(wardId && areaId && spotId && categoryId) {
                    $.ajax({
                        url: 'api/check_duplicate.php',
                        type: 'GET',
                        data: {
                            ward_id: wardId,
                            area_id: areaId,
                            spot_id: spotId,
                            category_id: categoryId
                        },
                        dataType: 'json',
                        success: function(data) {
                            if(data.is_repeated) {
                                $('#duplicateMessage').text(data.message);
                                $('#duplicateWarning').show();
                            } else {
                                $('#duplicateWarning').hide();
                            }
                        }
                    });
                }
            }
            
            $('#spot_id, #category_id').change(checkDuplicate);
            
            // Client-side form validation
            $('#complaintForm').submit(function(e) {
                var title = $('#title').val().trim();
                var description = $('#description').val().trim();
                
                if(title.length < 10) {
                    alert('Title must be at least 10 characters long');
                    e.preventDefault();
                    return false;
                }
                
                if(description.length < 20) {
                    alert('Description must be at least 20 characters long');
                    e.preventDefault();
                    return false;
                }
                
                if(!$('#ward_id').val() || !$('#area_id').val() || !$('#spot_id').val()) {
                    alert('Please select Ward, Area, and Spot');
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
        });
        </script>
    </main>
</body>
</html>