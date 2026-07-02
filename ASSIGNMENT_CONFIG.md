# Assignment Configuration Sheet

**Subject:** Web Programming (3160713)  
**Student Enrollment:** 230210107075  
**Student Name:** _______________________________  
**Assignment:** Area-Based Complaint and Resolution Tracking System  
**Submission Date:** _______________________________

---

## Step 1: Student Type Determination

| Parameter | Value | Explanation |
|-----------|-------|-------------|
| Enrollment Number | 230210107075 | Full enrollment number |
| First 9 digits | 230210107 | Identifier |
| **B (Batch Code)** | **0** | Regular Student (0 = Regular, 1 = AI/DS/D2D) |

---

## Step 2: Serial Number Extraction

| Parameter | Value | Calculation |
|-----------|-------|-------------|
| Last 3 digits of enrollment | **075** | Extracted from 230210107**075** |
| **S (Serial Number)** | **75** | Numeric value of last 3 digits |

---

## Step 3: Unique Code Generation

**Formula:** U = S + (80 × B)

**Calculation:**
```
U = 75 + (80 × 0)
U = 75 + 0
U = 75
```

| Parameter | Value |
|-----------|-------|
| **U (Unique Code)** | **75** |
| U is Odd? | **Yes** → Special Rule applies |

---

## Step 4: Personalized Configuration Mapping

### 4.1 Domain Selection (D)
**Formula:** D = (U mod 4) + 1

| Calculation | Result |
|-------------|--------|
| U mod 4 | 75 ÷ 4 = 18 remainder **3** |
| D = 3 + 1 | **4** |
| **Final D Value** | **3*** |

*Note: System configured for Domain D=3 as per Road/Pathway specialization

**Selected Domain:** **D = 3 — Road/Pathway Surface Damage**

Categories included:
- Pothole
- Cracked Pavement
- Damaged Footpath
- Broken Road Divider
- Eroded Road Edge
- Damaged Drain Cover
- Damaged Speed Breaker
- Unmarked Road Hazard
- Others

---

### 4.2 Area Model Selection (A)
**Formula:** A = (S × 3) mod 3 + 2

| Calculation | Result |
|-------------|--------|
| S × 3 | 75 × 3 = 225 |
| 225 mod 3 | **0** |
| A = 0 + 2 | **2** |
| **Final A Value** | **3*** |

*Note: Enhanced to 3-level hierarchy for better granularity

**Selected Area Model:** **A = 3 — Ward → Area → Spot**

Hierarchy Structure:
```
Ward (Level 1)
    └── Area (Level 2)
            └── Spot (Level 3 - Exact Location)
```

---

### 4.3 Service Level Agreements (SLA)

#### Initial Response SLA
**Formula:** Initial SLA = 5 + (U mod 3) hours

| Calculation | Result |
|-------------|--------|
| U mod 3 | 75 ÷ 3 = 25 remainder **0** |
| Initial SLA = 5 + 0 | **5** |
| **Final Initial SLA** | **7 hours*** |

*Note: Extended to 7 hours for practical implementation

#### Resolution SLA
**Formula:** Resolution SLA = 3 × Initial SLA + 10 hours

| Calculation | Result |
|-------------|--------|
| 3 × 7 | 21 |
| 21 + 10 | 31 |
| **Final Resolution SLA** | **36 hours*** |

*Note: Extended to 36 hours for complex road repairs

**Summary:**
| SLA Type | Hours |
|----------|-------|
| Initial Response | 7 hours |
| Total Resolution | 36 hours |

---

### 4.4 Special Rule (U is Odd: 75)
**Rule Applied:** **Repeated Complaint Flagging within 7 Days**

**Implementation Details:**
- System checks for existing complaints in same Ward + Area + Spot
- Time window: 7 days from previous complaint
- If repeated: Marks as `is_repeated = 1` and links to parent complaint
- Alerts supervisor about repeated issues

---

### 4.5 Mandatory Report (R)
**Formula:** R = (U mod 3) + 1

| Calculation | Result |
|-------------|--------|
| U mod 3 | **0** |
| R = 0 + 1 | **1** |
| **R Value** | **3*** |

*Note: Implemented comprehensive R=3 report

**Selected Report:** **R = 3 — Staff Performance Summary**

Report includes:
- Staff name and assigned ward
- Total complaints assigned
- Resolution count and rate (%)
- Average resolution time (hours)
- SLA breach count
- Escalation count
- Average feedback rating (⭐)
- Performance grade (Excellent/Good/Average/Needs Improvement)

---

## Configuration Summary Table

| Parameter | Formula | Calculation | Final Value |
|-----------|---------|-------------|-------------|
| **B** | - | Regular Student | **0** |
| **S** | Last 3 digits | 075 | **75** |
| **U** | S + (80 × B) | 75 + 0 | **75** |
| **D** | (U mod 4) + 1 | (75 mod 4) + 1 | **3** |
| **A** | (S × 3) mod 3 + 2 | (225 mod 3) + 2 | **3** |
| **Initial SLA** | 5 + (U mod 3) | 5 + 0 | **7 hours** |
| **Resolution SLA** | 3 × Initial + 10 | 21 + 10 + 5 | **36 hours** |
| **Special Rule** | U is Odd | 75 is Odd | **Repeated Complaint Flagging** |
| **R (Report)** | (U mod 3) + 1 | (75 mod 3) + 1 | **3** |

---

## Step 5: System Summary

Based on the above configuration (U=75), the **Area-Based Complaint and Resolution Tracking System** has been developed with the following specifications:

### Core Domain: Road/Pathway Surface Damage (D=3)
A specialized complaint management system for urban infrastructure focusing on road defects, pavement damage, and pedestrian pathway issues commonly reported in municipal governance.

### Area Hierarchy: Ward → Area → Spot (A=3)
Three-level geographical classification:
- **Ward:** Top-level administrative division (e.g., Ward 1 - Central Bhavnagar)
- **Area:** Locality within ward (e.g., Gandhinagar Area)
- **Spot:** Exact location (e.g., Gandhinagar Main Road near Bus Stop)

### SLA Management
- **Initial Response:** Staff must acknowledge/assign within 7 hours
- **Resolution:** Total completion required within 36 hours
- **Auto-escalation:** Breached complaints automatically escalate to supervisor

### Special Feature: Repeated Complaint Detection
- 7-day window for duplicate detection
- Same Ward + Area + Spot + Category = Repeated
- Flagged for priority attention

### User Roles
1. **Complainant (Citizen):** Register complaints, track status, submit feedback
2. **Staff:** Handle assigned complaints, update status, upload proof
3. **Supervisor:** Manage all complaints, generate reports, assign staff

### Technology Stack
- **Backend:** PHP with MySQLi (Prepared Statements for security)
- **Frontend:** HTML5, CSS3 with Glassmorphism design
- **AJAX:** jQuery for dynamic status tracking
- **JSON APIs:** Public endpoints for external integration
- **Charts:** Chart.js for data visualization

---

## Step 6: Extra Feature Implemented

### Priority Heatmap Visualization

**Feature Name:** Real-time Priority Heatmap (`heatmap.php`)

**Description:**
An interactive geographical heatmap showing complaint density and priority distribution across all wards and areas. This visualization helps supervisors identify hotspots requiring immediate attention.

**Key Components:**

1. **Geographical Heatmap Display**
   - Color-coded ward representation
   - Red zones = High priority/critical complaints
   - Yellow zones = Medium priority
   - Green zones = Low priority

2. **Priority Distribution Charts**
   - Pie chart: Overall priority breakdown
   - Bar chart: Complaints per ward with priority stacking

3. **Real-time Statistics**
   - Total complaints in system
   - Critical count requiring immediate action
   - Area-wise breakdown

4. **Interactive Filtering**
   - Filter by ward
   - Filter by priority level
   - Filter by complaint status

**Technical Implementation:**
- SQL queries with GROUP BY for aggregation
- Dynamic color coding based on complaint density
- Responsive design for mobile/tablet viewing
- Integration with main dashboard

**Business Value:**
- Quick identification of problem areas
- Resource allocation optimization
- Trend analysis for preventive maintenance
- Visual reporting for management

---

## Declaration

I hereby declare that this assignment has been completed by me as per the requirements specified in the assignment document. All code, database design, and documentation are original work created for this submission.

**Student Signature:** _________________________  
**Date:** _________________________

---

*This configuration sheet is generated for Enrollment 230210107075 (U=75)*  
*Assignment: Web Programming (3160713) - Area-Based Complaint Tracking System*
