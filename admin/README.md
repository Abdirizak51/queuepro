# 🏢 QueuePro — Smart Queue & Appointment Management System

<p align="center">
  <img src="https://img.shields.io/badge/Language-PHP%208.x-purple?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Architecture-Custom%20MVC%20%2F%20Modular-blue?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Security-CSRF%20%26%20RBAC%20Enabled-brightgreen?style=for-the-badge" />
</p>

---

## 📝 Overview
**QueuePro** is a production-ready, enterprise-grade Queue and Appointment Management System built with native PHP. The platform bridges the gap between digital appointment scheduling and real-time physical queue tracking. It features a highly secure administration workspace with strict Role-Based Access Control (RBAC), built-in CSRF validation, and dynamic data presentation filters.

---

## ✨ Core System Architecture & Features

### 🔐 1. Advanced Security & Access Control
* **Role-Based Access Control (RBAC):** Strict middleware layer (`requireRole()`) separating super-admins, staff members, and customers.
* **Exploit Mitigation:** Global token verification (`verifyCsrf()`) embedded across all critical POST controllers to block Cross-Site Request Forgery.
* **Input Sanitization:** Native strict variable mapping, integer-casting, and deep trimming for form fields (`$fullName`, `$email`, `$phone`).

### 📊 2. Operations & Queue Modules
* **Live Display Engine (`queue-display.php`):** Public-facing token/ticket announcement dashboard.
* **Live Counter Tracking (`queue-live.php`):** Back-end dynamic synchronization for counter desk officers.
* **Unified Appointment System (`appointments.php`):** Centralized logs for booking, monitoring, and updating branch entries.
* **Detailed Reporting Architecture (`reports.php`):** Comprehensive summaries of analytics and daily data performance.

---

## 📂 Repository File Structure

```text
├── admin/
│   ├── appointments.php     # Appointment scheduling panel
│   ├── dashboard.php        # Admin central KPIs
│   ├── queue-display.php    # Public monitoring token interface
│   ├── queue-live.php       # Live desk operations management
│   ├── users.php            # RBAC User accounts engine
│   └── tickets.php          # Customer service log trackers
├── api/                     # Backend endpoint integrations
├── config/                  # Database connections and constants
├── helpers/                 # Security scripts and core formatters
└── views/                   # Dynamic markup partials (header, footer)