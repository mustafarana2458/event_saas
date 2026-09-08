<div align="center">

# 🎉 EventSaaS — Multi-Tenant Event Management Platform

**A complete SaaS platform for managing marriage halls, banquets, and event companies — from a single venue to multi-branch enterprises.**

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mariadb.org/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)](#-license)

[Overview](#-overview) • [Features](#-features) • [Tech Stack](#-tech-stack) • [Setup](#-getting-started) • [Structure](#-project-structure) • [Screenshots](#-screenshots)

</div>

---

## 📖 Overview

**EventSaaS** is a production-style, multi-tenant SaaS application built to manage the full operational lifecycle of event and venue businesses — marriage halls, banquet companies, and multi-branch event organizations of any size.

The platform is architected around **two isolated control planes**:

- **🛡️ Super Admin Panel** — the SaaS owner's control tower for onboarding organizations, managing subscriptions, monitoring platform-wide revenue, and auditing activity across every tenant.
- **🏢 Organization Panel** — each tenant's private workspace for managing bookings, halls/venues, branches, clients, staff, payments, and invoices — fully isolated from every other tenant's data.

Built entirely with core **PHP + PDO + MySQL**, it demonstrates strong fundamentals in relational schema design, session security, role-based access control, and building a real multi-tenant architecture without relying on a heavy framework.

> Designed for event businesses of any scale — a single marriage hall or a large multi-branch operation — with room to scale into a fully commercial SaaS offering.

---

## ✨ Features

### 🏢 Multi-Tenant Architecture
- Fully isolated organizations (tenants), each with its own halls, staff, clients, and financial data
- Multi-branch support — a single organization can operate multiple physical locations, each with its own halls and managers
- Subscription plans (`basic`, `professional`, `enterprise`) with expiry tracking

### 👥 Role-Based Access Control
Four distinct roles with granular, enforced permissions:

| Feature | Super Admin | Org Admin | Manager | Staff |
|---|:---:|:---:|:---:|:---:|
| Manage Organizations | ✅ | ❌ | ❌ | ❌ |
| Activate / Deactivate Orgs & Admins | ✅ | ❌ | ❌ | ❌ |
| Platform Revenue Report | ✅ | ✅ | ✅ | ❌ |
| Manage Team Members | ❌ | ✅ | ❌ | ❌ |
| Create Events / Bookings | ❌ | ✅ | ✅ | ✅ |
| Delete Events | ❌ | ✅ | ✅ | ❌ |
| Manage Payments | ❌ | ✅ | ✅ | ✅ |
| Generate Invoices | ❌ | ✅ | ✅ | ❌ |
| Manage Halls / Branches | ❌ | ✅ | ✅ | ❌ |
| Manage Clients | ❌ | ✅ | ✅ | ✅ |

### 🛡️ Super Admin Panel
- Platform-wide dashboard with KPIs across all tenants
- Organization onboarding, activation/deactivation (instantly revokes access for all of that org's admins)
- Global admin management across every organization
- Platform-wide revenue reporting with monthly breakdowns
- System-wide activity/audit log

### 📅 Event & Booking Management
- Full interactive booking calendar with month navigation, per-day event dots, and hall/branch filtering
- Event lifecycle statuses: `tentative → confirmed → in_progress → completed / cancelled`
- Support for multiple event types: wedding, engagement, mehendi, valima, birthday, corporate, and more
- Time-slot booking (morning / afternoon / evening / full-day) to avoid double-booking halls

### 🏛️ Hall & Branch Management
- Manage multiple venues/halls per branch with capacity, amenities, and per-event pricing
- Multi-branch operations with per-branch managers and status control

### 💳 Payments & Invoicing
- Payment tracking with multiple methods (cash, bank transfer, cheque, online)
- Auto-numbered invoice generation (`INV-YYYY-ORGID-SEQ`) with line items, discounts, and tax
- Invoice statuses: draft, sent, paid, partial, overdue — with printable invoice view
- Automatic remaining-balance calculation per event

### 📊 Financial Reporting & Analytics
- Monthly/yearly financial reports per organization
- Revenue breakdown by payment method
- Pending vs. collected revenue tracking
- Animated bar charts and progress visualizations

### 👨‍👩‍👧 Client & Team Management
- Centralized client database (contact info, CNIC, address, booking history)
- Team member management with role assignment (org_admin / manager / staff)

### 🔒 Enterprise-Grade Security
- **bcrypt** password hashing for all accounts
- **CSRF protection** on all state-changing forms (token generated + verified per session)
- **SQL injection protection** — 100% PDO prepared statements, zero raw query concatenation
- **XSS protection** — all dynamic output sanitized via `htmlspecialchars`
- **Login rate-limiting** — automatic lockout after repeated failed attempts, tracked per email + IP
- **10-minute inactivity auto-logout** with a live client-side session timer
- **Server-side session validation** every 60 seconds (catches deactivation instantly, mid-session)
- **Cascading deactivation** — disabling an organization immediately locks out all of its admins
- Full **activity audit trail** for every login, logout, and critical action

### 📱 Responsive, Polished UI
- Dark, professional theme with a signature gold accent (`#C9A84C`)
- `Playfair Display` + `DM Sans` typography pairing
- Fully responsive — collapsible sidebar, touch-friendly calendar, adaptive stat cards and tables on mobile
- Smooth CSS animations (fade-up, slide-in) and interactive charts

### 💰 Localization
- Currency formatting built around **PKR (₨)**, easily adaptable to other currencies via a single config constant

---

## 🛠️ Tech Stack

<div align="center">

| Layer | Technology |
|---|---|
| **Backend** | ![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white) Procedural PHP with PDO |
| **Database** | ![MySQL](https://img.shields.io/badge/MySQL-MariaDB%2010.4-4479A1?style=flat-square&logo=mysql&logoColor=white) Relational schema with FK constraints & cascading rules |
| **Frontend** | ![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.2-7952B3?style=flat-square&logo=bootstrap&logoColor=white) ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white) ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white) |
| **JavaScript** | ![JavaScript](https://img.shields.io/badge/Vanilla_JS-F7DF1E?style=flat-square&logo=javascript&logoColor=black) AJAX (Fetch API) for live session checks & dynamic UI |
| **Icons & Fonts** | ![Font Awesome](https://img.shields.io/badge/Font_Awesome-6.5.0-528DD7?style=flat-square&logo=fontawesome&logoColor=white) Google Fonts (Playfair Display, DM Sans) |
| **Security** | bcrypt · CSRF tokens · PDO prepared statements · Rate limiting |
| **Architecture** | Multi-tenant SaaS · Role-Based Access Control (RBAC) · MVC-inspired module structure |

</div>

---

## 🚀 Getting Started

### Prerequisites
- PHP **8.0+** with the `PDO` and `pdo_mysql` extensions enabled
- MySQL **5.7+** or MariaDB **10.4+**
- A local server stack — [XAMPP](https://www.apachefriends.org/), [WAMP](https://www.wampserver.com/), or [Laragon](https://laragon.org/) (recommended for Windows)

### Installation

1. **Clone the repository**
   ```bash
   git clone <your-repo-url> event_saas
   cd event_saas
   ```

2. **Place the project in your server's web root**
   ```bash
   # e.g. for XAMPP on Windows
   # C:\xampp\htdocs\event_saas
   ```

3. **Create the database**
   - Open phpMyAdmin (or your preferred MySQL client)
   - Create a database named `event_saas`
   - Import the schema: `event_saas.sql`

4. **Configure the app**

   Open `includes/config.php` and update the values to match your environment:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'event_saas');

   // ⚠️ Update this to your actual local/production URL
   define('APP_URL', 'http://localhost/event_saas');
   ```

5. **Run it**

   Visit the app in your browser:
   ```
   http://localhost/event_saas
   ```
   You'll be redirected automatically to the login page based on your session state.

6. **Log in**

   Demo credentials are seeded via `event_saas.sql`. Set your own Super Admin and Org Admin credentials directly in the database (passwords are bcrypt-hashed) before sharing access with anyone, and never commit real credentials to source control.

   > 🔐 **Security note:** Rotate the seeded demo passwords immediately in any non-local environment, and set `'secure' => true` on the session cookie in `includes/config.php` once served over HTTPS.

---

## 📁 Project Structure

```
event_saas/
├── index.php                      # Entry point — routes to the correct dashboard/login
├── event_saas.sql                 # Full database schema + seed data
│
├── api/                           # AJAX endpoints
│   ├── check_session.php          # Live session validity check
│   ├── dashboard_stats.php        # Dashboard KPI data
│   ├── get_events.php             # Calendar event data
│   ├── toggle_admin.php           # Activate/deactivate an admin
│   ├── toggle_org.php             # Activate/deactivate an organization
│   └── update_event.php           # Inline event updates
│
├── assets/
│   ├── css/dashboard.css          # Global dark theme + responsive styles
│   └── js/app.js                  # Session timer, UI interactions
│
├── includes/                      # Shared PHP includes
│   ├── config.php                 # DB config, session, security helpers
│   ├── dashboard_styles.php       # Shared CSS/font/script includes
│   ├── session_check.php          # Client-side inactivity timer
│   ├── superadmin_session.php     # Super admin session bootstrap
│   ├── admin_sidebar.php          # Org admin navigation
│   ├── superadmin_sidebar.php     # Super admin navigation
│   └── topbar.php                 # Shared top navigation bar
│
├── modules/
│   ├── auth/
│   │   ├── login.php               # Unified login (super admin + org admin)
│   │   └── logout.php
│   │
│   ├── superadmin/                 # 🛡️ SaaS owner control panel
│   │   ├── dashboard.php           # Platform-wide KPIs
│   │   ├── organizations.php       # Tenant onboarding & management
│   │   ├── admins.php              # Global admin management
│   │   ├── revenue.php             # Platform revenue analytics
│   │   ├── activity_logs.php       # System-wide audit trail
│   │   └── profile.php
│   │
│   └── admin/                      # 🏢 Organization (tenant) workspace
│       ├── dashboard.php           # Org-level KPIs & upcoming events
│       ├── calendar.php            # Interactive booking calendar
│       ├── events.php              # Event/booking CRUD
│       ├── halls.php               # Hall/venue management
│       ├── branches.php            # Multi-branch management
│       ├── clients.php             # Client database
│       ├── team.php                # Staff/team management
│       ├── payments.php            # Payment tracking
│       ├── invoices.php            # Invoice generation & printing
│       ├── finance_report.php      # Financial analytics
│       └── profile.php
│
└── uploads/                        # User-uploaded assets (logos, avatars)
```

---

## 🗄️ Database Schema

The schema (see `event_saas.sql`) is fully normalized with foreign-key constraints and cascading rules to keep tenant data consistent and isolated:

`organizations` · `branches` · `admins` · `halls` · `clients` · `events` · `payments` · `invoices` · `invoice_items` · `super_admins` · `activity_logs` · `login_attempts` · `user_sessions`

Every tenant-scoped table carries an `organization_id` foreign key with `ON DELETE CASCADE`, guaranteeing clean, complete data isolation between tenants.

---

## 📸 Screenshots

> _Add product screenshots below to showcase the UI — the dark gold theme photographs especially well for a portfolio or client pitch._

<div align="center">

| Login | Super Admin Dashboard |
|---|---|
| ![Login Screen](docs/screenshots/login.png) | ![Super Admin Dashboard](docs/screenshots/superadmin-dashboard.png) |

| Org Dashboard | Booking Calendar |
|---|---|
| ![Org Dashboard](docs/screenshots/admin-dashboard.png) | ![Calendar](docs/screenshots/calendar.png) |

| Invoices | Finance Report |
|---|---|
| ![Invoice](docs/screenshots/invoice.png) | ![Finance Report](docs/screenshots/finance-report.png) |

</div>

*Place image files in `docs/screenshots/` using the filenames above, or update the paths to match your own naming.*

---

## 🗺️ Roadmap

- [ ] REST API layer for a future mobile app
- [ ] WhatsApp/SMS/email booking reminders
- [ ] Online payment gateway integration
- [ ] PDF export for invoices and reports
- [ ] Client-facing booking portal

---

## 📄 License

This project is proprietary software developed for demonstration and client/portfolio purposes. Contact the author for licensing or collaboration inquiries.

---

<div align="center">

**Built with ❤️ using core PHP — proof that solid architecture doesn't need a heavyweight framework.**

</div>
