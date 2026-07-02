-- =====================================================
-- Area-Based Complaint & Resolution Tracking System
-- Database Schema for Road/Pathway Surface Damage Domain
-- Enrollment: 230210107075 | U=75 (Odd: Repeated Complaint Flagging)
-- Domain D=3: Road/Pathway Surface Damage
-- Area Model A=3: Ward → Area → Spot
-- Initial SLA: 7 hours | Resolution SLA: 36 hours
-- Mandatory Report R=3: Staff Performance Summary
-- =====================================================

CREATE DATABASE IF NOT EXISTS complaint_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE complaint_db;

-- -----------------------------------------------------
-- Table: users (Complainants, Staff, Supervisors)
-- -----------------------------------------------------
CREATE TABLE users (
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
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: area_master (Ward → Area → Spot Hierarchy)
-- -----------------------------------------------------
CREATE TABLE area_master (
    area_id INT AUTO_INCREMENT PRIMARY KEY,
    area_name VARCHAR(100) NOT NULL,
    area_type ENUM('ward', 'area', 'spot') NOT NULL,
    parent_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES area_master(area_id) ON DELETE CASCADE,
    INDEX idx_type (area_type),
    INDEX idx_parent (parent_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: complaint_categories (Road/Pathway Damage Categories)
-- -----------------------------------------------------
CREATE TABLE complaint_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: status_master (Workflow Statuses)
-- -----------------------------------------------------
CREATE TABLE status_master (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(30) UNIQUE NOT NULL,
    display_order INT DEFAULT 0,
    color_code VARCHAR(7) DEFAULT '#6b7280',
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: complaints
-- -----------------------------------------------------
CREATE TABLE complaints (
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
    reopen_approved TINYINT(1) DEFAULT NULL,
    reopen_remarks TEXT,
    FOREIGN KEY (complainant_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES complaint_categories(category_id),
    FOREIGN KEY (ward_id) REFERENCES area_master(area_id),
    FOREIGN KEY (area_id) REFERENCES area_master(area_id),
    FOREIGN KEY (spot_id) REFERENCES area_master(area_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id),
    FOREIGN KEY (repeated_parent_id) REFERENCES complaints(complaint_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_submitted (submitted_at),
    INDEX idx_sla_initial (initial_sla_deadline),
    INDEX idx_sla_resolution (resolution_sla_deadline),
    INDEX idx_complainant (complainant_id),
    INDEX idx_assigned (assigned_to),
    INDEX idx_ward (ward_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: complaint_attachments
-- -----------------------------------------------------
CREATE TABLE complaint_attachments (
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
    INDEX idx_complaint (complaint_id),
    INDEX idx_type (file_type)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: complaint_history (Status Change Log)
-- -----------------------------------------------------
CREATE TABLE complaint_history (
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
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: assignments
-- -----------------------------------------------------
CREATE TABLE assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    staff_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    remarks TEXT,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id),
    INDEX idx_complaint (complaint_id),
    INDEX idx_staff (staff_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Table: feedback
-- -----------------------------------------------------
CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    complainant_id INT NOT NULL,
    rating TINYINT CHECK (rating BETWEEN 1 AND 5),
    remarks TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (complainant_id) REFERENCES users(user_id),
    INDEX idx_complaint (complaint_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Insert Default Data
-- -----------------------------------------------------

-- Status Master
INSERT INTO status_master (status_name, display_order, color_code) VALUES
('submitted', 1, '#f59e0b'),
('verified', 2, '#3b82f6'),
('assigned', 3, '#8b5cf6'),
('in_progress', 4, '#06b6d4'),
('resolved', 5, '#10b981'),
('closed', 6, '#64748b'),
('reopened', 7, '#f97316'),
('escalated', 8, '#dc2626');

-- Road/Pathway Surface Damage Categories
INSERT INTO complaint_categories (category_name, description, is_active) VALUES
('Pothole', 'Deep or wide holes in road surface causing vehicle damage or accidents', 1),
('Cracked Pavement', 'Surface cracks, spider web cracks, or longitudinal cracks in road surface', 1),
('Damaged Footpath', 'Broken tiles, uneven surfaces, or missing sections of pedestrian walkways', 1),
('Broken Road Divider', 'Damaged or missing road dividers, median barriers, or lane separators', 1),
('Eroded Road Edge', 'Washed out or crumbling edges of roads, especially near drains or water flow', 1),
('Damaged Drain Cover', 'Broken, missing, or loose drain/manhole covers posing safety hazards', 1),
('Damaged Speed Breaker', 'Worn out, broken, or unmarked speed bumps needing repair', 1),
('Unmarked Road Hazard', 'Sharp curves, dangerous intersections, or hazards without proper signage', 1),
('Others', 'Any other road or pathway surface damage not listed above', 1);

-- Sample Wards (Top Level)
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Ward 1 - Central Bhavnagar', 'ward', NULL, 1),
('Ward 2 - Ghogha Circle', 'ward', NULL, 1),
('Ward 3 - Victoria Park', 'ward', NULL, 1),
('Ward 4 - Talaja Road', 'ward', NULL, 1);

-- Sample Areas under Ward 1
SET @ward1 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 1 - Central Bhavnagar' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Gandhinagar Area', 'area', @ward1, 1),
('Kalanala Area', 'area', @ward1, 1),
('Panwadi Area', 'area', @ward1, 1);

-- Sample Areas under Ward 2
SET @ward2 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 2 - Ghogha Circle' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Ghogha Circle East', 'area', @ward2, 1),
('Ghogha Circle West', 'area', @ward2, 1),
('Maruti Nagar', 'area', @ward2, 1);

-- Sample Areas under Ward 3
SET @ward3 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 3 - Victoria Park' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Victoria Park Main', 'area', @ward3, 1),
('Nilambaug Area', 'area', @ward3, 1),
('Sir Takhtasinhji Hospital Area', 'area', @ward3, 1);

-- Sample Areas under Ward 4
SET @ward4 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 4 - Talaja Road' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Talaja Road Main', 'area', @ward4, 1),
('Ruvapari Area', 'area', @ward4, 1),
('Sidsar Area', 'area', @ward4, 1);

-- Sample Spots under Gandhinagar Area
SET @gandhinagar = (SELECT area_id FROM area_master WHERE area_name = 'Gandhinagar Area' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Gandhinagar Main Road', 'spot', @gandhinagar, 1),
('Gandhinagar Circle', 'spot', @gandhinagar, 1),
('Gandhinagar Railway Crossing', 'spot', @gandhinagar, 1),
('Gandhinagar Bus Stop', 'spot', @gandhinagar, 1);

-- Sample Spots under Ghogha Circle East
SET @ghogha_east = (SELECT area_id FROM area_master WHERE area_name = 'Ghogha Circle East' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Ghogha Circle Center', 'spot', @ghogha_east, 1),
('Ghogha Circle Market', 'spot', @ghogha_east, 1),
('Ghogha Circle Bus Stand', 'spot', @ghogha_east, 1);

-- Sample Spots under Victoria Park Main
SET @victoria_main = (SELECT area_id FROM area_master WHERE area_name = 'Victoria Park Main' LIMIT 1);
INSERT INTO area_master (area_name, area_type, parent_id, is_active) VALUES
('Victoria Park Entrance', 'spot', @victoria_main, 1),
('Victoria Park Lake Side', 'spot', @victoria_main, 1),
('Victoria Park Walking Track', 'spot', @victoria_main, 1);

-- Default Supervisor Account (password: Admin@1234)
INSERT INTO users (name, email, password_hash, role, phone, is_active) VALUES
('System Administrator', 'admin@complaint.gov', '$2y$10$hoB0Xl0hxso8lkbJbhNfretPJNLEZ228QhxKjs4.kvQ0c/PVaIvGi', 'supervisor', '9876543210', 1);

-- Default Staff Accounts (password: Staff@1234)
INSERT INTO users (name, email, password_hash, role, phone, ward_id, is_active) VALUES
('Staff Member 1', 'staff1@complaint.gov', '$2y$10$yKXfLb1LtfY4//wQDAyXvuAPaiQB7uDJPGdmEj7F3xXZFaI8dTiT6', 'staff', '9876543211', 1, 1),
('Staff Member 2', 'staff2@complaint.gov', '$2y$10$yKXfLb1LtfY4//wQDAyXvuAPaiQB7uDJPGdmEj7F3xXZFaI8dTiT6', 'staff', '9876543212', 2, 1),
('Staff Member 3', 'staff3@complaint.gov', '$2y$10$yKXfLb1LtfY4//wQDAyXvuAPaiQB7uDJPGdmEj7F3xXZFaI8dTiT6', 'staff', '9876543213', 3, 1);

-- Demo Complainant Accounts (password: User@1234)
INSERT INTO users (name, email, password_hash, role, phone, ward_id, is_active) VALUES
('Demo Citizen 1', 'citizen1@complaint.gov', '$2y$10$EwtcCyy.N3v51PZ9DSjqSeRM5.q8JhiNIngEUJhr4j8oE6e6NiUua', 'complainant', '9876500001', 1, 1),
('Demo Citizen 2', 'citizen2@complaint.gov', '$2y$10$EwtcCyy.N3v51PZ9DSjqSeRM5.q8JhiNIngEUJhr4j8oE6e6NiUua', 'complainant', '9876500002', 2, 1);

-- -----------------------------------------------------
-- Sample Complaints (Road/Pathway Domain)
-- -----------------------------------------------------

-- Set variables for complainants and categories
SET @citizen1 = (SELECT user_id FROM users WHERE email = 'citizen1@complaint.gov' LIMIT 1);
SET @citizen2 = (SELECT user_id FROM users WHERE email = 'citizen2@complaint.gov' LIMIT 1);
SET @staff1 = (SELECT user_id FROM users WHERE email = 'staff1@complaint.gov' LIMIT 1);
SET @staff2 = (SELECT user_id FROM users WHERE email = 'staff2@complaint.gov' LIMIT 1);
SET @pothole = (SELECT category_id FROM complaint_categories WHERE category_name = 'Pothole' LIMIT 1);
SET @cracked = (SELECT category_id FROM complaint_categories WHERE category_name = 'Cracked Pavement' LIMIT 1);
SET @footpath = (SELECT category_id FROM complaint_categories WHERE category_name = 'Damaged Footpath' LIMIT 1);
SET @drain = (SELECT category_id FROM complaint_categories WHERE category_name = 'Damaged Drain Cover' LIMIT 1);
SET @divider = (SELECT category_id FROM complaint_categories WHERE category_name = 'Broken Road Divider' LIMIT 1);

-- Get area IDs
SET @ward1 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 1 - Central Bhavnagar' LIMIT 1);
SET @ward2 = (SELECT area_id FROM area_master WHERE area_name = 'Ward 2 - Ghogha Circle' LIMIT 1);
SET @gandhinagar_area = (SELECT area_id FROM area_master WHERE area_name = 'Gandhinagar Area' LIMIT 1);
SET @ghogha_area = (SELECT area_id FROM area_master WHERE area_name = 'Ghogha Circle East' LIMIT 1);
SET @gandhinagar_spot = (SELECT area_id FROM area_master WHERE area_name = 'Gandhinagar Main Road' LIMIT 1);
SET @ghogha_spot = (SELECT area_id FROM area_master WHERE area_name = 'Ghogha Circle Center' LIMIT 1);
SET @victoria_area = (SELECT area_id FROM area_master WHERE area_name = 'Victoria Park Main' LIMIT 1);
SET @victoria_spot = (SELECT area_id FROM area_master WHERE area_name = 'Victoria Park Walking Track' LIMIT 1);

-- Sample Complaint 1: SUBMITTED (New, unassigned)
INSERT INTO complaints (
    complaint_code, complainant_id, category_id, ward_id, area_id, spot_id,
    exact_location, title, description, priority, status,
    initial_sla_deadline, resolution_sla_deadline, submitted_at
) VALUES (
    'ROAD-2026-0001', @citizen1, @pothole, @ward1, @gandhinagar_area, @gandhinagar_spot,
    'Near Gandhinagar Bus Stop, opposite SBI Bank',
    'Deep Pothole Causing Vehicle Damage',
    'There is a large pothole approximately 3 feet wide and 1 foot deep on the main road near the bus stop. Several vehicles have been damaged, including my two-wheeler. This is dangerous especially during night time when visibility is low.',
    'high', 'submitted',
    DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 36 HOUR), NOW()
);

-- Sample Complaint 2: ASSIGNED (Assigned to staff, initial SLA met)
INSERT INTO complaints (
    complaint_code, complainant_id, category_id, ward_id, area_id, spot_id,
    exact_location, title, description, priority, status,
    initial_sla_deadline, resolution_sla_deadline, submitted_at, assigned_at, assigned_to
) VALUES (
    'ROAD-2026-0002', @citizen2, @cracked, @ward2, @ghogha_area, @ghogha_spot,
    'Ghogha Circle Center, near Krishna Hotel',
    'Cracked Pavement Creating Accident Risk',
    'The road surface at Ghogha Circle has developed multiple cracks forming a spider web pattern. During monsoon, water collects in these cracks making it slippery. Yesterday a scooter slipped due to this. Immediate repair needed.',
    'medium', 'assigned',
    DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 36 HOUR),
    DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR), @staff2
);

-- Sample Complaint 3: IN_PROGRESS (Work started)
INSERT INTO complaints (
    complaint_code, complainant_id, category_id, ward_id, area_id, spot_id,
    exact_location, title, description, priority, status,
    initial_sla_deadline, resolution_sla_deadline, submitted_at, assigned_at, in_progress_at, assigned_to
) VALUES (
    'ROAD-2026-0003', @citizen1, @footpath, @ward1, @victoria_area, @victoria_spot,
    'Victoria Park Walking Track, near Lake Side',
    'Damaged Footpath Tiles - Senior Citizens at Risk',
    'The walking track in Victoria Park has several broken tiles which are hazardous especially for senior citizens who walk here daily. My elderly father almost tripped yesterday. The tiles need urgent replacement.',
    'high', 'in_progress',
    DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 36 HOUR),
    DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 15 HOUR), DATE_SUB(NOW(), INTERVAL 5 HOUR), @staff1
);

-- Sample Complaint 4: RESOLVED (Completed with feedback)
INSERT INTO complaints (
    complaint_code, complainant_id, category_id, ward_id, area_id, spot_id,
    exact_location, title, description, priority, status,
    initial_sla_deadline, resolution_sla_deadline, submitted_at, assigned_at,
    in_progress_at, resolved_at, assigned_to
) VALUES (
    'ROAD-2026-0004', @citizen2, @drain, @ward2, @ghogha_area, @ghogha_spot,
    'Near Ghogha Circle Bus Stand, main entrance',
    'Broken Drain Cover - Safety Hazard',
    'The drain cover near the bus stand is completely broken and anyone could fall in, especially children and elderly. This needs immediate attention before any accident occurs. I have temporarily placed a warning sign.',
    'critical', 'resolved',
    DATE_ADD(NOW(), INTERVAL 7 HOUR), DATE_ADD(NOW(), INTERVAL 36 HOUR),
    DATE_SUB(NOW(), INTERVAL 72 HOUR), DATE_SUB(NOW(), INTERVAL 65 HOUR),
    DATE_SUB(NOW(), INTERVAL 48 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR), @staff2
);

-- Sample Complaint 5: ESCALATED (SLA breached)
INSERT INTO complaints (
    complaint_code, complainant_id, category_id, ward_id, area_id, spot_id,
    exact_location, title, description, priority, status,
    initial_sla_deadline, resolution_sla_deadline, submitted_at, escalated_at
) VALUES (
    'ROAD-2026-0005', @citizen1, @divider, @ward1, @gandhinagar_area, @gandhinagar_spot,
    'Gandhinagar Main Road median, near Railway Crossing',
    'Damaged Road Divider - Vehicles Crossing Dangerously',
    'The road divider/median barrier is damaged and vehicles are dangerously crossing over to the wrong side. This is causing near-miss accidents daily. The divider needs immediate repair or replacement.',
    'critical', 'escalated',
    DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR),
    DATE_SUB(NOW(), INTERVAL 48 HOUR), DATE_SUB(NOW(), INTERVAL 6 HOUR)
);

-- Add complaint history entries for status changes
INSERT INTO complaint_history (complaint_id, old_status, new_status, updated_by, remarks, updated_at) VALUES
(LAST_INSERT_ID() - 3, 'submitted', 'assigned', @staff2, 'Assigned to Staff 2 for immediate repair', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(LAST_INSERT_ID() - 2, 'assigned', 'in_progress', @staff1, 'Repair work started - tiles being replaced', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(LAST_INSERT_ID() - 1, 'in_progress', 'resolved', @staff2, 'Drain cover replaced with new one. Work completed successfully.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(LAST_INSERT_ID(), 'submitted', 'escalated', @staff1, 'ESCALATED: Resolution SLA breached. No staff assigned yet.', DATE_SUB(NOW(), INTERVAL 6 HOUR));

-- Add feedback for resolved complaint
SET @resolved_complaint = (SELECT complaint_id FROM complaints WHERE complaint_code = 'ROAD-2026-0004' LIMIT 1);
SET @citizen2_id = (SELECT user_id FROM users WHERE email = 'citizen2@complaint.gov' LIMIT 1);

INSERT INTO feedback (complaint_id, complainant_id, rating, remarks, submitted_at) VALUES
(@resolved_complaint, @citizen2_id, 4, 'Great work! The drain cover was replaced within 2 days. Very satisfied with the quick response.', DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- -----------------------------------------------------
-- Views for Reports
-- -----------------------------------------------------

-- View for SLA tracking
CREATE VIEW vw_sla_status AS
SELECT 
    c.complaint_id,
    c.complaint_code,
    c.title,
    c.status,
    c.submitted_at,
    c.initial_sla_deadline,
    c.resolution_sla_deadline,
    c.assigned_to,
    u.name as assigned_staff,
    CASE 
        WHEN c.status IN ('resolved', 'closed') THEN 'Completed'
        WHEN NOW() > c.resolution_sla_deadline THEN 'Escalated'
        WHEN NOW() > c.initial_sla_deadline THEN 'Initial SLA Breached'
        ELSE 'Within SLA'
    END as sla_status,
    TIMESTAMPDIFF(HOUR, c.submitted_at, NOW()) as hours_elapsed
FROM complaints c
LEFT JOIN users u ON c.assigned_to = u.user_id;

-- View for staff performance
CREATE VIEW vw_staff_performance AS
SELECT 
    u.user_id,
    u.name as staff_name,
    COUNT(DISTINCT c.complaint_id) as total_assigned,
    COUNT(DISTINCT CASE WHEN c.status IN ('resolved', 'closed') THEN c.complaint_id END) as resolved_count,
    AVG(CASE WHEN c.status IN ('resolved', 'closed') THEN TIMESTAMPDIFF(HOUR, c.assigned_at, c.resolved_at) END) as avg_resolution_hours,
    COUNT(DISTINCT CASE WHEN c.status = 'escalated' THEN c.complaint_id END) as escalation_count,
    COUNT(DISTINCT CASE WHEN NOW() > c.resolution_sla_deadline AND c.status NOT IN ('resolved', 'closed') THEN c.complaint_id END) as sla_breach_count
FROM users u
LEFT JOIN complaints c ON c.assigned_to = u.user_id
WHERE u.role = 'staff'
GROUP BY u.user_id, u.name;

-- -----------------------------------------------------
-- Stored Procedures
-- -----------------------------------------------------

DELIMITER //

-- Procedure to check for repeated complaints (Special Rule: U is Odd)
CREATE PROCEDURE sp_check_repeated_complaint(
    IN p_ward_id INT,
    IN p_area_id INT,
    IN p_spot_id INT,
    IN p_category_id INT,
    OUT p_is_repeated BOOLEAN,
    OUT p_parent_id INT
)
BEGIN
    SELECT complaint_id INTO p_parent_id
    FROM complaints
    WHERE ward_id = p_ward_id 
    AND area_id = p_area_id 
    AND spot_id = p_spot_id
    AND category_id = p_category_id
    AND status NOT IN ('resolved', 'closed')
    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY submitted_at ASC
    LIMIT 1;
    
    SET p_is_repeated = IF(p_parent_id IS NOT NULL, TRUE, FALSE);
END //

-- Procedure to escalate complaints past SLA
CREATE PROCEDURE sp_auto_escalate()
BEGIN
    UPDATE complaints
    SET status = 'escalated', escalated_at = NOW()
    WHERE status NOT IN ('resolved', 'closed', 'escalated')
    AND NOW() > resolution_sla_deadline;
END //

DELIMITER ;

-- -----------------------------------------------------
-- Triggers
-- -----------------------------------------------------

DELIMITER //

-- Trigger to log status changes
CREATE TRIGGER trg_complaint_status_change
AFTER UPDATE ON complaints
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO complaint_history (complaint_id, old_status, new_status, updated_by, remarks, updated_at)
        VALUES (NEW.complaint_id, OLD.status, NEW.status, COALESCE(@current_user_id, 1), CONCAT('Status changed from ', OLD.status, ' to ', NEW.status), NOW());
    END IF;
END //

DELIMITER ;
