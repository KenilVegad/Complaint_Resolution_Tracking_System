# Area-Based Complaint & Resolution Tracking System

## System Features

### 1. Authentication & Authorization
- **Three Role System**: Complainant, Staff, Supervisor
- **Password Hashing**: bcrypt encryption
- **Session Management**: PHP sessions with security headers
- **Cookie Feature**: Remember last selected ward filter (7 days)
- **Role-based Access Control**: Enforced on every page

### 2. Area Master Management (Ward → Area → Spot)
- Hierarchical area structure (3 levels deep)
- Dependent dropdowns via AJAX
- Add/Edit/Disable functionality for supervisor
- Filter persistence using cookies

### 3. Complaint Categories
**Domain**: Road / Pathway Surface Damage
- Pothole
- Cracked Pavement
- Damaged Footpath
- Broken Road Divider
- Eroded Road Edge
- Damaged Drain Cover
- Damaged Speed Breaker
- Unmarked Road Hazard
- Others

### 4. Complaint Registration
- Auto-generated Complaint ID: `ROAD-YYYY-XXXX`
- **Duplicate Detection** (Special Rule): Flags repeated complaints within 7 days for same Ward+Area+Spot+Category
- SLA deadline calculation (7h initial, 36h resolution)
- File upload with validation (JPG, PNG, PDF, max 5MB)
- Client-side and server-side validation

### 5. Complaint Workflow & Status History
**Valid Status Transitions**:
```
Submitted → Verified → Assigned → In Progress → Resolved → Closed
                     ↓
                  Escalated (auto on SLA breach)
```
- Complete history logging with timestamps
- Timeline visualization
- Status change validation
- Remarks at each transition

### 6. SLA Tracking & Escalation
- Initial Response SLA: 7 hours
- Resolution SLA: 36 hours
- Auto-escalation when SLA is breached
- Visual SLA indicators (amber for late, red for escalated)
- Dashboard alerts for approaching deadlines

### 7. Staff Assignment
- Supervisor assigns complaints to staff
- Staff can only view assigned complaints
- Assignment history tracking
- Workload balancing

### 8. File Upload Module
- Secure file renaming
- MIME type validation
- Size limits (5MB)
- Separate folders for complaint proof and action proof
- Image thumbnails in complaint detail view

### 9. PHP JSON API Endpoints
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/get_complaint.php` | GET | Get complaint by ID (public tracking) |
| `/api/get_areas.php` | GET | Get areas by ward ID (AJAX) |
| `/api/get_spots.php` | GET | Get spots by area ID (AJAX) |
| `/api/check_duplicate.php` | GET | Check for repeated complaints |

### 10. AJAX Features (Detailed)

| Feature Name | Location | Description |
|--------------|----------|-------------|
| **Dependent Ward→Area Dropdown** | `complaint.php` | When ward is selected, jQuery AJAX fetches areas for that ward from `api/get_areas.php` and populates the area dropdown dynamically |
| **Dependent Area→Spot Dropdown** | `complaint.php` | When area is selected, jQuery AJAX fetches spots for that area from `api/get_spots.php` and populates the spot dropdown |
| **Live Duplicate Complaint Check** | `complaint.php` | As user selects location and category, AJAX calls `api/check_duplicate.php` to check for existing complaints in same Ward+Area+Spot+Category within 7 days |
| **Complaint Status Live Search** | `track.php` | User enters complaint code, AJAX fetches real-time status from `api/track_complaint.php` without page reload, displays full complaint details with timeline |

**AJAX Implementation Pattern:**
```javascript
$.ajax({
    url: 'api/endpoint.php',
    data: { param: value },
    dataType: 'json',
    success: function(response) {
        // Update UI dynamically
    }
});
```

### 11. Mandatory Report (R=3): Staff Performance Summary
- Individual staff statistics
- Resolution rate with visual progress bars
- Average resolution time
- SLA breach count per staff
- Escalation count
- Performance rating (Excellent/Good/Average/Needs Improvement)
- Printable report format
- Chart.js visualization

### 12. Extra Feature: Priority Heatmap

**File:** `heatmap.php`

The Priority Heatmap is an interactive visualization tool that displays complaint density across different wards using color-coded intensity mapping. It aggregates complaints by location and priority level, presenting a geographical heatmap where red zones indicate high-critical complaint clusters, yellow for medium priority areas, and green for low-priority regions. The feature includes dynamic charts showing priority distribution, real-time statistics cards, and filtering capabilities by ward and priority level. This visualization helps supervisors quickly identify problem hotspots, allocate resources effectively, and make data-driven decisions for municipal maintenance planning. The heatmap uses Chart.js for interactive charts and responsive CSS grid for the geographical representation, updating in real-time as new complaints are registered.

---

## API Endpoints (JSON APIs)

| File | Method | Parameters | Description | Sample Response |
|------|--------|------------|-------------|---------------|
| `api/get_complaint.php` | GET | `code` (e.g., ROAD-2026-0001) | Public API to track complaint by code. Returns full details including status, SLA, location, assigned staff. | `{"complaint_code":"ROAD-2026-0001","title":"Pothole","status":"resolved","priority":"high","category_name":"Pothole","ward_name":"Ward 1","area_name":"Gandhinagar","spot_name":"Main Road","exact_location":"Near Bus Stop","submitted_at":"2026-04-28 10:00:00","initial_sla_deadline":"2026-04-28 17:00:00","resolution_sla_deadline":"2026-04-29 22:00:00","assigned_staff":"John Doe","is_repeated":false}` |
| `api/pending_by_area.php` | GET | `ward_id` (optional) | Returns pending complaints grouped by ward and area with counts. Used for supervisor dashboard and resource planning. | `{"generated_at":"2026-04-30 22:00:00","total_pending":42,"data":[{"ward_id":1,"ward_name":"Ward 1","total_pending":12,"areas":[{"area_id":5,"area_name":"Gandhinagar","pending_count":5,"critical_count":1,"high_count":2}]}]}` |
| `api/get_areas.php` | GET | `ward_id` | Returns all areas for a given ward ID. Used in dependent dropdown AJAX. | `[{"area_id":5,"area_name":"Gandhinagar Area"},{"area_id":6,"area_name":"Kalanala Area"}]` |
| `api/get_spots.php` | GET | `area_id` | Returns all spots for a given area ID. Used in dependent dropdown AJAX. | `[{"spot_id":12,"spot_name":"Gandhinagar Main Road"},{"spot_id":13,"spot_name":"Gandhinagar Circle"}]` |
| `api/check_duplicate.php` | GET | `ward_id`, `area_id`, `spot_id`, `category_id` | Checks for repeated complaints in same location+category within 7 days. Returns is_repeated boolean and parent complaint ID. | `{"is_repeated":true,"parent_complaint_id":15,"existing_count":1,"message":"Similar complaint exists"}` |

---

## Sample Test Data / Demo Accounts

### Demo Login Credentials

| Role | Email | Password | Name | Phone |
|------|-------|----------|------|-------|
| **Supervisor** | admin@complaint.gov | Admin@1234 | System Administrator | 9876543210 |
| **Staff (Ward 1)** | staff1@complaint.gov | Staff@1234 | Staff Member 1 | 9876543211 |
| **Staff (Ward 2)** | staff2@complaint.gov | Staff@1234 | Staff Member 2 | 9876543212 |
| **Staff (Ward 3)** | staff3@complaint.gov | Staff@1234 | Staff Member 3 | 9876543213 |
| **Complainant** | citizen1@complaint.gov | User@1234 | Demo Citizen 1 | 9876500001 |
| **Complainant** | citizen2@complaint.gov | User@1234 | Demo Citizen 2 | 9876500002 |

### Pre-loaded Sample Complaints

| Code | Title | Category | Status | Priority | Complainant | Assigned Staff |
|------|-------|----------|--------|----------|-------------|----------------|
| **ROAD-2026-0001** | Deep Pothole Causing Vehicle Damage | Pothole | **Submitted** (New) | High | citizen1@complaint.gov | Unassigned |
| **ROAD-2026-0002** | Cracked Pavement Creating Accident Risk | Cracked Pavement | **Assigned** | Medium | citizen2@complaint.gov | Staff 2 |
| **ROAD-2026-0003** | Damaged Footpath Tiles - Senior Citizens at Risk | Damaged Footpath | **In Progress** | High | citizen1@complaint.gov | Staff 1 |
| **ROAD-2026-0004** | Broken Drain Cover - Safety Hazard | Damaged Drain Cover | **Resolved** | Critical | citizen2@complaint.gov | Staff 2 |
| **ROAD-2026-0005** | Damaged Road Divider - Vehicles Crossing Dangerously | Broken Road Divider | **Escalated** | Critical | citizen1@complaint.gov | Unassigned (SLA Breached) |

**Sample Feedback:** Complaint ROAD-2026-0004 has a 4-star rating with remarks: "Great work! The drain cover was replaced within 2 days. Very satisfied with the quick response."


---

## Technology Stack

### Frontend
- HTML5 (semantic markup)
- CSS3 with custom properties
- JavaScript (ES6+)
- jQuery 3.x (AJAX)
- Chart.js (Data visualization)
- Font Awesome 6 (Icons)
- Google Fonts (Typography)

### Backend
- PHP 8.x
- MySQL 8.x
- MySQLi (Prepared statements)
- PHP Sessions & Cookies

### Security Features
- SQL Injection Prevention (Prepared statements)
- XSS Prevention (htmlspecialchars)
- Password Hashing (bcrypt)
- File Upload Validation (MIME type, extension, size)
- CSRF Protection (Session validation)
- Input Sanitization

---

## Database Schema

### Tables
1. **users** - User accounts (complainant, staff, supervisor)
2. **area_master** - Hierarchical location data (ward, area, spot)
3. **complaint_categories** - Road damage categories
4. **status_master** - Workflow status definitions
5. **complaints** - Main complaint records with SLA tracking
6. **complaint_attachments** - File uploads
7. **complaint_history** - Status change audit log
8. **assignments** - Staff assignment records
9. **feedback** - Post-resolution ratings

### Key Features
- Foreign key constraints
- Proper indexing for performance
- Triggers for auto-logging
- Views for reports

---

## Installation Guide

### Prerequisites
- XAMPP / WAMP / MAMP
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web browser (Chrome/Firefox/Edge)

### Steps

1. **Place files in web root**
   ```
   Copy all files to: c:\xampp\htdocs\complaint-system\
   ```

2. **Create database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create database: `complaint_db`
   - Or run: `http://localhost/complaint-system/setup.php`

3. **Run setup**
   - Visit: `http://localhost/complaint-system/setup.php`
   - This creates all tables and default data

4. **Login**
   - Supervisor: `admin@complaint.gov` / `admin123`
   - Staff: `staff1@complaint.gov` / `staff123`


---

## Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Supervisor | admin@complaint.gov | Admin@1234 |
| Staff | staff1@complaint.gov | Staff@1234 |
| Staff | staff2@complaint.gov | Staff@1234 |
| Staff | staff3@complaint.gov | Staff@1234 |
| Complainant | citizen1@complaint.gov | User@1234 |
| Complainant | citizen2@complaint.gov | User@1234 |

---

## API Usage Examples

### Get Complaint Status
```bash
curl "http://localhost/complaint-system/api/get_complaint.php?code=ROAD-2026-0004"
```

### Get Areas for Ward
```javascript
$.get('api/get_areas.php', {ward_id: 1}, function(data) {
    console.log(data); // [{"area_id": 5, "area_name": "Gandhinagar Area"}, ...]
});
```

### Check Duplicate
```javascript
$.get('api/check_duplicate.php', {
    ward_id: 1,
    area_id: 5,
    spot_id: 12,
    category_id: 3
}, function(response) {
    if(response.is_repeated) {
        alert('Similar complaint exists!');
    }
});
```

---

## Browser Support
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## Author

###Kenil Vegad 

Computer Engineering Student 

GitHub: https://github.com/Kenil Vegad

This project was designed and developed as a personal full-stack web application to demonstrate complaint management, workflow automation, role-based authentication, and reporting features using PHP, MySQL, JavaScript, AJAX, and Chart.js.

---


## Acknowledgments
- Font Awesome for icons
- Google Fonts for typography
- Chart.js for data visualization
- XAMPP for development environment
