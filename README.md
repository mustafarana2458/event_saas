# EventSaaS — Events Management System
## Complete SaaS Platform with Multi-Tenant Support

## In this we not only making this product for Marriage Halls but for all the event companies either they are small or a large ones (multi branches)

## 👥 USER ROLES & PERMISSIONS

| Feature            | Super Admin | Org Admin | Manager | Staff |
|--------------------|:-----------:|:---------:|:-------:|:-----:|
| Manage Orgs        | ✅          | ❌        | ❌      | ❌    |
| Activate/Deactivate| ✅          | ❌        | ❌      | ❌    |
| Manage All Admins  | ✅          | ❌        | ❌      | ❌    |
| Revenue Report     | ✅          | ✅        | ✅      | ❌    |
| Manage Team        | ❌          | ✅        | ❌      | ❌    |
| Create Events      | ❌          | ✅        | ✅      | ✅    |
| Delete Events      | ❌          | ✅        | ✅      | ❌    |
| Manage Payments    | ❌          | ✅        | ✅      | ✅    |
| Generate Invoices  | ❌          | ✅        | ✅      | ❌    |
| Manage Halls       | ❌          | ✅        | ✅      | ❌    |
| Manage Clients     | ❌          | ✅        | ✅      | ✅    |

---

## 🔒 SECURITY FEATURES

1. **10-Minute Inactivity Logout** — Automatic logout after 10 min of no activity
2. **Server-side Session Check** — Every 60 seconds, checks if admin is still active
3. **Organization Deactivation** — Deactivating an org instantly blocks ALL its admins
4. **Admin Deactivation** — Individual admin accounts can be disabled
5. **Password Hashing** — bcrypt hashing for all passwords
6. **SQL Injection Protection** — PDO prepared statements throughout
7. **XSS Protection** — All output is sanitized with htmlspecialchars

---

## 📁 FILE STRUCTURE

```
marriage-hall-saas/
├── index.php                   # Entry point / redirect
├── assets/
│   └── css/
│       └── dashboard.css       # Global dark theme styles
├── includes/
│   ├── config.php              # DB config, helpers, session logic 
│   │         (must change the App Url to run  it in production or local)
│   ├── dashboard_styles.php    # CSS/font includes
│   ├── superadmin_sidebar.php  # Super admin navigation
│   ├── admin_sidebar.php       # Hall admin navigation
│   ├── topbar.php              # Top navigation bar
│   └── session_check.php      # JS session timer + server check
├── modules/
│   ├── auth/
│   │   ├── login.php           # Unified login for all users
│   │   └── logout.php          # Logout handler
│   ├── superadmin/
│   │   ├── dashboard.php       # Super admin overview
│   │   ├── organizations.php   # Manage all orgs
│   │   ├── admins.php          # Manage all admins
│   │   ├── revenue.php         # Platform revenue report
│   │   ├── activity_logs.php   # System audit trail
│   │   └── profile.php         # Super admin profile
│   └── admin/
│       ├── dashboard.php       # Hall admin dashboard
│       ├── calendar.php        # Full booking calendar
│       ├── events.php          # Event management
│       ├── payments.php        # Payment tracking
│       ├── invoices.php        # Invoice generation & print
│       ├── finance_report.php  # Financial analytics
│       ├── clients.php         # Client management
│       ├── halls.php           # Hall/venue management
│       ├── team.php            # Team member management
│       └── profile.php         # Admin profile settings
└── api/
    ├── check_session.php       # Session validity check (AJAX)
    ├── toggle_org.php          # Activate/deactivate org (AJAX)
    ├── toggle_admin.php        # Activate/deactivate admin (AJAX)
    └── get_events.php          # Calendar events data (AJAX)
```

---

## 💰 CURRENCY
All amounts displayed in **PKR (Pakistani Rupee — ₨)**

---

## 📱 RESPONSIVE
Fully mobile-responsive with:
- Collapsible sidebar on mobile
- Touch-friendly calendar
- Responsive stat cards (2-column on mobile)
- Mobile-optimized tables with horizontal scroll

---

## 🎨 THEME
- Dark professional theme
- Gold accent color (#C9A84C)
- Playfair Display + DM Sans fonts
- Smooth CSS animations (fadeUp, slideIn)
- Animated bar charts, progress bars
- Interactive calendar with event dots

---



##  CREDIENTIALS
- Super Admin:  subhanaziz406@gmail.com / Subhan@@0406
- Hall Admin:demoadmin@gamil.com / DemoOrginization@@admin

---

## ❓ SUPPORT
 For any further knowladge contact subhanaziz406@gmail.com
