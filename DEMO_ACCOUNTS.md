# Demo Accounts - Citizen Complaint Portal

This document contains all demo login credentials for testing the Complaint Management System.

## System Overview

**Application URL:** `http://localhost/complaint-system/`

**Database Setup:** Import `database.sql` to create all demo data automatically.

---

## Supervisor / Admin Account

| Field | Value |
|-------|-------|
| **Email** | admin@complaint.gov |
| **Password** | Admin@1234 |
| **Role** | Supervisor |
| **Name** | System Administrator |
| **Phone** | 9876543210 |

### Supervisor Access:
- View all complaints across all wards
- Assign complaints to staff
- Generate performance reports
- Manage staff and area master data
- View priority heatmaps
- Access API endpoints

---

## Staff Accounts

### Staff Member 1 (Ward 1 - Central Bhavnagar)
| Field | Value |
|-------|-------|
| **Email** | staff1@complaint.gov |
| **Password** | Staff@1234 |
| **Role** | Staff |
| **Name** | Staff Member 1 |
| **Phone** | 9876543211 |
| **Assigned Ward** | Ward 1 - Central Bhavnagar |

### Staff Member 2 (Ward 2 - Ghogha Circle)
| Field | Value |
|-------|-------|
| **Email** | staff2@complaint.gov |
| **Password** | Staff@1234 |
| **Role** | Staff |
| **Name** | Staff Member 2 |
| **Phone** | 9876543212 |
| **Assigned Ward** | Ward 2 - Ghogha Circle |

### Staff Member 3 (Ward 3 - Victoria Park)
| Field | Value |
|-------|-------|
| **Email** | staff3@complaint.gov |
| **Password** | Staff@1234 |
| **Role** | Staff |
| **Name** | Staff Member 3 |
| **Phone** | 9876543213 |
| **Assigned Ward** | Ward 3 - Victoria Park |

### Staff Access:
- View assigned complaints
- Update complaint status
- Upload action proof images
- Mark complaints as resolved
- View own performance stats

---

## Complainant (Citizen) Accounts

### Demo Citizen 1 (Ward 1)
| Field | Value |
|-------|-------|
| **Email** | citizen1@complaint.gov |
| **Password** | User@1234 |
| **Role** | Complainant |
| **Name** | Demo Citizen 1 |
| **Phone** | 9876500001 |
| **Ward** | Ward 1 - Central Bhavnagar |

### Demo Citizen 2 (Ward 2)
| Field | Value |
|-------|-------|
| **Email** | citizen2@complaint.gov |
| **Password** | User@1234 |
| **Role** | Complainant |
| **Name** | Demo Citizen 2 |
| **Phone** | 9876500002 |
| **Ward** | Ward 2 - Ghogha Circle |

### Complainant Access:
- Register new complaints
- View own complaint history
- Track complaint status
- Submit feedback after resolution
- Upload complaint proof images

---

## Sample Complaints (Pre-loaded)

The database includes 5 sample complaints with realistic road/pathway issues:

| Complaint Code | Category | Status | Priority | Assigned To |
|----------------|----------|--------|----------|-------------|
| ROAD-2026-0001 | Pothole | **Submitted** (New) | High | Unassigned |
| ROAD-2026-0002 | Cracked Pavement | **Assigned** | Medium | Staff 2 |
| ROAD-2026-0003 | Damaged Footpath | **In Progress** | High | Staff 1 |
| ROAD-2026-0004 | Damaged Drain Cover | **Resolved** | Critical | Staff 2 |
| ROAD-2026-0005 | Broken Road Divider | **Escalated** | Critical | Unassigned |

### Complaint Details:

1. **ROAD-2026-0001** - Deep Pothole (Submitted)
   - Location: Gandhinagar Main Road, near Bus Stop
   - Complainant: Demo Citizen 1
   - Status: New, awaiting assignment

2. **ROAD-2026-0002** - Cracked Pavement (Assigned)
   - Location: Ghogha Circle, near Krishna Hotel
   - Complainant: Demo Citizen 2
   - Assigned: Staff Member 2

3. **ROAD-2026-0003** - Damaged Footpath (In Progress)
   - Location: Victoria Park Walking Track
   - Complainant: Demo Citizen 1
   - Assigned: Staff Member 1

4. **ROAD-2026-0004** - Broken Drain Cover (Resolved)
   - Location: Ghogha Circle Bus Stand
   - Complainant: Demo Citizen 2
   - Assigned: Staff Member 2
   - **Feedback**: 4/5 stars - "Great work! Replaced within 2 days."

5. **ROAD-2026-0005** - Damaged Road Divider (Escalated)
   - Location: Gandhinagar Main Road median
   - Complainant: Demo Citizen 1
   - Status: SLA Breached, escalated to supervisor

---

## API Endpoints (Public Access)

### 1. Track Complaint by Code
```
GET http://localhost/complaint-system/api/get_complaint.php?code=ROAD-2026-0001
```
**No login required** - Returns complaint details, status, SLA info

### 2. Pending Complaints by Area
```
GET http://localhost/complaint-system/api/pending_by_area.php
GET http://localhost/complaint-system/api/pending_by_area.php?ward_id=1
```
**No login required** - Returns grouped pending complaints by ward/area

---

## Quick Test Scenarios

### Scenario 1: Citizen Registers Complaint
1. Login as `citizen1@complaint.gov` / `User@1234`
2. Click "Register Complaint"
3. Fill in details and submit
4. Track complaint status

### Scenario 2: Supervisor Assigns Complaint
1. Login as `admin@complaint.gov` / `Admin@1234`
2. View "All Complaints"
3. Click "Assign" on unassigned complaint
4. Select staff member and assign

### Scenario 3: Staff Resolves Complaint
1. Login as `staff1@complaint.gov` / `Staff@1234`
2. View "My Work"
3. Update status to "In Progress"
4. Upload action proof
5. Mark as "Resolved"

### Scenario 4: Citizen Submits Feedback
1. Login as `citizen2@complaint.gov` / `User@1234`
2. View resolved complaint (ROAD-2026-0004)
3. Submit star rating and remarks
4. View feedback in Staff Performance Report

### Scenario 5: Track Complaint Publicly
1. No login needed
2. Go to `track.php`
3. Enter complaint code: `ROAD-2026-0004`
4. View status and timeline

---

## Password Hash Reference

| Password | Bcrypt Hash |
|----------|-------------|
| Admin@1234 | `$2y$10$hoB0Xl0hxso8lkbJbhNfretPJNLEZ228QhxKjs4.kvQ0c/PVaIvGi` |
| Staff@1234 | `$2y$10$yKXfLb1LtfY4//wQDAyXvuAPaiQB7uDJPGdmEj7F3xXZFaI8dTiT6` |
| User@1234 | `$2y$10$EwtcCyy.N3v51PZ9DSjqSeRM5.q8JhiNIngEUJhr4j8oE6e6NiUua` |

---

## System Configuration

| Setting | Value |
|---------|-------|
| Initial SLA | 7 hours |
| Resolution SLA | 36 hours |
| Domain | Road/Pathway Surface Damage |
| Area Model | Ward → Area → Spot |
| Special Rule | Repeated Complaint Flagging (7 days) |

---

## Support

For database reset, re-import `database.sql` to restore all demo data.
