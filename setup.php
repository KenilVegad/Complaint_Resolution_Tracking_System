<?php
/**
 * System Setup Script
 * Run this to initialize the database and create default admin account
 */

// Database Configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'complaint_db';

echo "<h2>Complaint Management System - Setup</h2>";
echo "<pre>";

// Create connection
try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✓ Connected to MySQL\n";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "✓ Database '$dbName' created/exists\n";
    }
    
    // Select database
    $conn->select_db($dbName);
    
    // Step 1: Create base tables (no foreign keys or self-referencing)
    $baseTables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('complainant', 'staff', 'supervisor') NOT NULL DEFAULT 'complainant',
            phone VARCHAR(15),
            ward_id INT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB",
        
        "area_master" => "CREATE TABLE IF NOT EXISTS area_master (
            area_id INT AUTO_INCREMENT PRIMARY KEY,
            area_name VARCHAR(100) NOT NULL,
            area_type ENUM('ward', 'area', 'spot') NOT NULL,
            parent_id INT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (area_type),
            INDEX idx_parent (parent_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB",
        
        "complaint_categories" => "CREATE TABLE IF NOT EXISTS complaint_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB",
        
        "status_master" => "CREATE TABLE IF NOT EXISTS status_master (
            status_id INT AUTO_INCREMENT PRIMARY KEY,
            status_name VARCHAR(30) UNIQUE NOT NULL,
            display_order INT DEFAULT 0,
            color_code VARCHAR(7) DEFAULT '#6b7280',
            is_active TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB"
    ];
    
    foreach ($baseTables as $name => $sql) {
        if ($conn->query($sql)) {
            echo "✓ Table '$name' created\n";
        } else {
            echo "✗ Error creating table '$name': " . $conn->error . "\n";
        }
    }
    
    // Step 2: Create complaints table (references base tables)
    $sql = "CREATE TABLE IF NOT EXISTS complaints (
        complaint_id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_code VARCHAR(20) UNIQUE NOT NULL,
        complainant_id INT NOT NULL,
        category_id INT NOT NULL,
        ward_id INT NOT NULL,
        area_id INT NOT NULL,
        spot_id INT NOT NULL,
        exact_location VARCHAR(255),
        title VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        status ENUM('submitted', 'verified', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened', 'escalated') DEFAULT 'submitted',
        is_repeated TINYINT(1) DEFAULT 0,
        repeated_parent_id INT NULL,
        initial_sla_deadline DATETIME NOT NULL,
        resolution_sla_deadline DATETIME NOT NULL,
        assigned_to INT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        verified_at DATETIME NULL,
        assigned_at DATETIME NULL,
        in_progress_at DATETIME NULL,
        resolved_at DATETIME NULL,
        closed_at DATETIME NULL,
        escalated_at DATETIME NULL,
        reopened_at DATETIME NULL,
        FOREIGN KEY (complainant_id) REFERENCES users(user_id),
        FOREIGN KEY (category_id) REFERENCES complaint_categories(category_id),
        FOREIGN KEY (ward_id) REFERENCES area_master(area_id),
        FOREIGN KEY (area_id) REFERENCES area_master(area_id),
        FOREIGN KEY (spot_id) REFERENCES area_master(area_id),
        FOREIGN KEY (assigned_to) REFERENCES users(user_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_submitted (submitted_at)
    ) ENGINE=InnoDB";
    
    if ($conn->query($sql)) {
        echo "✓ Table 'complaints' created\n";
    } else {
        echo "✗ Error creating table 'complaints': " . $conn->error . "\n";
    }
    
    // Step 3: Create dependent tables (reference complaints)
    $dependentTables = [
        "complaint_attachments" => "CREATE TABLE IF NOT EXISTS complaint_attachments (
            attachment_id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type ENUM('complaint_proof', 'action_proof') NOT NULL,
            original_name VARCHAR(255),
            file_size INT,
            mime_type VARCHAR(100),
            uploaded_by INT NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(user_id),
            INDEX idx_complaint (complaint_id)
        ) ENGINE=InnoDB",
        
        "complaint_history" => "CREATE TABLE IF NOT EXISTS complaint_history (
            history_id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            old_status VARCHAR(30),
            new_status VARCHAR(30) NOT NULL,
            updated_by INT NOT NULL,
            remarks TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(user_id),
            INDEX idx_complaint (complaint_id),
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB",
        
        "assignments" => "CREATE TABLE IF NOT EXISTS assignments (
            assignment_id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            staff_id INT NOT NULL,
            assigned_by INT NOT NULL,
            assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            remarks TEXT,
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
            FOREIGN KEY (staff_id) REFERENCES users(user_id),
            FOREIGN KEY (assigned_by) REFERENCES users(user_id)
        ) ENGINE=InnoDB",
        
        "feedback" => "CREATE TABLE IF NOT EXISTS feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            complaint_id INT NOT NULL,
            complainant_id INT NOT NULL,
            rating TINYINT CHECK (rating BETWEEN 1 AND 5),
            remarks TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
            FOREIGN KEY (complainant_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB"
    ];
    
    foreach ($dependentTables as $name => $sql) {
        if ($conn->query($sql)) {
            echo "✓ Table '$name' created\n";
        } else {
            echo "✗ Error creating table '$name': " . $conn->error . "\n";
        }
    }
    
    // Step 4: Add foreign key to area_master (self-referencing)
    $sql = "ALTER TABLE area_master ADD FOREIGN KEY (parent_id) REFERENCES area_master(area_id) ON DELETE CASCADE";
    $conn->query($sql); // Ignore error if already exists
    echo "✓ Foreign keys added\n";
    
    // Insert default status master data
    $statuses = [
        ['submitted', 1, '#f59e0b'],
        ['verified', 2, '#3b82f6'],
        ['assigned', 3, '#8b5cf6'],
        ['in_progress', 4, '#06b6d4'],
        ['resolved', 5, '#10b981'],
        ['closed', 6, '#64748b'],
        ['reopened', 7, '#f97316'],
        ['escalated', 8, '#dc2626']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO status_master (status_name, display_order, color_code) VALUES (?, ?, ?)");
    foreach ($statuses as $status) {
        $stmt->bind_param("sis", $status[0], $status[1], $status[2]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✓ Status master data inserted\n";
    
    // Insert default categories (Road/Pathway Surface Damage)
    $categories = [
        ['Pothole', 'Deep or wide holes in road surface causing vehicle damage or accidents'],
        ['Cracked Pavement', 'Surface cracks, spider web cracks, or longitudinal cracks in road surface'],
        ['Damaged Footpath', 'Broken tiles, uneven surfaces, or missing sections of pedestrian walkways'],
        ['Broken Road Divider', 'Damaged or missing road dividers, median barriers, or lane separators'],
        ['Eroded Road Edge', 'Washed out or crumbling edges of roads, especially near drains or water flow'],
        ['Damaged Drain Cover', 'Broken, missing, or loose drain/manhole covers posing safety hazards'],
        ['Damaged Speed Breaker', 'Worn out, broken, or unmarked speed bumps needing repair'],
        ['Unmarked Road Hazard', 'Sharp curves, dangerous intersections, or hazards without proper signage'],
        ['Others', 'Any other road or pathway surface damage not listed above']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO complaint_categories (category_name, description) VALUES (?, ?)");
    foreach ($categories as $cat) {
        $stmt->bind_param("ss", $cat[0], $cat[1]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✓ Complaint categories inserted (Road/Pathway Surface Damage domain)\n";
    
    // Insert sample wards
    $wards = ['Ward 1 - Central Bhavnagar', 'Ward 2 - Ghogha Circle', 'Ward 3 - Victoria Park', 'Ward 4 - Talaja Road'];
    $wardIds = [];
    $stmt = $conn->prepare("INSERT IGNORE INTO area_master (area_name, area_type, parent_id, is_active) VALUES (?, 'ward', NULL, 1)");
    foreach ($wards as $ward) {
        $stmt->bind_param("s", $ward);
        $stmt->execute();
        $wardIds[$ward] = $conn->insert_id ?: $conn->query("SELECT area_id FROM area_master WHERE area_name = '$ward'")->fetch_assoc()['area_id'];
    }
    $stmt->close();
    echo "✓ Sample wards inserted\n";
    
    // Insert sample areas under Ward 1
    $areasWard1 = ['Gandhinagar Area', 'Kalanala Area', 'Panwadi Area'];
    $stmt = $conn->prepare("INSERT IGNORE INTO area_master (area_name, area_type, parent_id, is_active) VALUES (?, 'area', ?, 1)");
    $ward1Id = $conn->query("SELECT area_id FROM area_master WHERE area_name = 'Ward 1 - Central Bhavnagar' LIMIT 1")->fetch_assoc()['area_id'] ?? 0;
    if($ward1Id) {
        foreach ($areasWard1 as $area) {
            $stmt->bind_param("si", $area, $ward1Id);
            $stmt->execute();
        }
    }
    $stmt->close();
    echo "✓ Sample areas inserted\n";
    
    // Insert default admin account
    $adminEmail = 'admin@complaint.gov';
    $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password_hash, role, phone, is_active) VALUES ('System Administrator', ?, ?, 'supervisor', '9876543210', 1)");
    $stmt->bind_param("ss", $adminEmail, $adminPass);
    $stmt->execute();
    $stmt->close();
    echo "✓ Default admin account created (admin@complaint.gov / admin123)\n";
    
    // Insert default staff accounts
    $staffAccounts = [
        ['Staff Member 1', 'staff1@complaint.gov', '9876543211'],
        ['Staff Member 2', 'staff2@complaint.gov', '9876543212'],
        ['Staff Member 3', 'staff3@complaint.gov', '9876543213']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password_hash, role, phone, is_active) VALUES (?, ?, ?, 'staff', ?, 1)");
    foreach ($staffAccounts as $staff) {
        $hashedPass = password_hash('staff123', PASSWORD_BCRYPT);
        $stmt->bind_param("ssss", $staff[0], $staff[1], $hashedPass, $staff[2]);
        $stmt->execute();
    }
    $stmt->close();
    echo "✓ Default staff accounts created (staff1@complaint.gov / staff123, etc.)\n";
    
    // Create uploads directory
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✓ Uploads directory created\n";
    }
    
    $conn->close();
    
    echo "\n========================================\n";
    echo "SETUP COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n\n";
    echo "Default Login Credentials:\n";
    echo "- Supervisor: admin@complaint.gov / admin123\n";
    echo "- Staff: staff1@complaint.gov / staff123\n";
    echo "         staff2@complaint.gov / staff123\n";
    echo "         staff3@complaint.gov / staff123\n\n";
    echo "<a href='login.php' style='font-size: 18px; color: #00d4ff;'>Click here to go to Login Page</a>\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
