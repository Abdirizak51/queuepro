# 🏢 QueuePro — Enterprise Smart Queue & Appointment Management System

<p align="center">
  <img src="https://img.shields.io/badge/Language-PHP%208.x-purple?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Architecture-Custom%20MVC%20%2F%20Modular-blue?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Security-CSRF%20%26%20RBAC%20Enabled-brightgreen?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Database-MySQL%20%2F%20PDO-orange?style=for-the-badge&logo=mysql" />
</p>

---

## 📝 Project Overview
**QueuePro** is a robust, production-ready, native PHP application engineered to streamline business operations by bridging physical customer service queues with digital pre-booked appointments. Built with strict structural modularity, the system ensures seamless optimization across multi-branch setups, dynamic counter screens, and user registration hubs.

The application leverages standard Object-Oriented paradigms and automated routing abstractions to deliver an enterprise-grade experience for managers, service agents, and end consumers.

---

## ✨ Advanced System Architecture & Capabilities

### 🔐 1. High-Tier Security Implementation
* **Role-Based Access Control (RBAC):** Strict operational separation utilizing a core access middleware (`requireRole()`). Restricts dashboard accessibility securely into distinct permissions for `ROLE_ADMIN`, `ROLE_STAFF`, and `ROLE_CUSTOMER`.
* **CSRF Exploit Protection:** Automated validation tokens (`verifyCsrf()`) embedded globally across all POST state changes to eliminate unauthorized request injections.
* **Data Sanitization Pipeline:** Comprehensive data scrubbing using type-casting, spatial trimming (`trim()`), and filter validations (`FILTER_VALIDATE_EMAIL`) before interaction with the database storage layer.

### 📊 2. Operations & Multi-Channel Queue Management
* **Live Display Monitor (`admin/queue-display.php`):** Public-facing, auto-refreshing presentation token board designed for customer waiting areas.
* **Live Counter Controller (`admin/queue-live.php`):** Fast-paced, reactive interface for active counter desk officers to call, transfer, or complete tokens.
* **Unified Appointment System:** Centralized booking scheduler supporting time-slot configuration, automated user notification queues, and dynamic multi-branch routing.
* **Audit & Performance Logs:** Native reporting system (`admin/reports.php`) and persistent loggers (`admin/logs.php`) for data auditing and business performance tracking.

---

## 📂 Structural Directory Breakdown

The codebase follows a scalable corporate standard layout, keeping layout scripts separate from underlying business logic controllers:

```text
├── admin/                 # High-Level Management Workspace
│   ├── appointments.php   # Central schedule and reservation tracking
│   ├── branches.php       # Company branch registry and parameters
│   ├── dashboard.php      # Main administrative metric counters & KPIs
│   ├── logs.php           # Audit trails for technical operations
│   ├── queue-display.php  # Public display grid for client areas
│   ├── queue-live.php     # Real-time service counter control engine
│   ├── reports.php        # Comprehensive data and speed analytics
│   ├── services.php       # Dynamic company service configuration
│   ├── settings.php       # Global instance configurations
│   ├── tickets.php        # Active customer service tokens
│   └── users.php          # Core RBAC User account management
├── api/                   # REST Data Endpoints
│   ├── queue-status.php   # Real-time state checkers for displays
│   ├── ticket-status.php  # Ticket lookup query service
│   └── v1.php             # Core API interface routing gateway
├── config/                # Platform Configuration Layer
│   ├── app.php            # Core application properties & environment bindings
│   └── database.php       # Secure PDO database connector instance
├── customer/              # Customer Client Area Portal
│   ├── appointments.php   # Personal booking schedules
│   ├── dashboard.php      # Client workspace landing page
│   ├── my-tickets.php     # Historical active digital ticket items
│   └── take-ticket.php    # Instant ticket/token generation wizard
├── helpers/               # Global Utility & Helper Layer
│   ├── auth.php           # Session tracking and permissions checker
│   ├── csrf.php           # Automated anti-forgery token engine
│   └── functions.php      # Reusable string, array, and template formatters
├── staff/                 # Desk Agent Dedicated Workspace
│   ├── appointments.php   # Assigned client appointments checklist
│   ├── dashboard.php      # Desk-agent operational control panel
│   └── queue.php          # Individual ticket distribution queue
├── views/partials/        # Extensible Layout Template Partials
│   ├── footer.php         # Reusable structural document footer
│   ├── header.php         # Global navigation blocks and document headers
│   └── user-form.php      # Abstracted dynamic profile forms layout
├── bootstrap.php          # Core global dependency initialization script
├── database.sql           # Complete structured layout database export schema
├── index.php              # Public entry point router
├── login.php              # Core gateway portal security gate
├── register.php           # Customer sign-up engine
└── reset-password.php     # Account recovery validation handler
