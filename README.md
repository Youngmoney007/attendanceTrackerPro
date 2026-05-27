# AttendTrack Pro – Attendance & Performance Tracker
## Complete Setup Guide

---

## 📁 Folder Structure

```
attendance_tracker/
├── index.php                   # Login page
├── logout.php                  # Logout handler
├── setup_passwords.php         # One-time password setup (DELETE after use)
├── database.sql                # Full DB creation script
│
├── includes/
│   ├── config.php              # DB credentials & app settings
│   ├── auth.php                # Session / login / role helpers
│   ├── helpers.php             # Utility functions
│   ├── nav.php                 # Shared sidebar + topbar
│   └── footer.php             # Shared footer + JS
│
├── employee/
│   ├── dashboard.php           # Employee home (clock-in widget, stats, chart)
│   ├── attendance.php          # Personal attendance history + export
│   ├── leave.php               # Apply and track leave requests
│   ├── performance.php         # KPI scores + trend chart
│   └── notifications.php      # In-app notifications
│
├── admin/
│   ├── dashboard.php           # Admin overview (all stats, charts, alerts)
│   ├── employees.php           # Employee CRUD (add/edit/deactivate)
│   ├── attendance_overview.php # Daily & monthly attendance tables + export
│   ├── leave_management.php    # Approve / reject leave requests
│   ├── performance_admin.php   # KPI management + employee scoring
│   └── reports.php             # Monthly summary reports + print/export
│
├── api/
│   ├── attendance.php          # Check-in / check-out POST handler
│   └── export.php              # CSV export endpoint (all types)
│
└── assets/
    └── css/
        └── style.css           # Complete design system (dark theme, responsive)
```

---

## ⚙️ Requirements

- **XAMPP** (or WAMP / LAMP / MAMP) with:
  - PHP 8.1+
  - MySQL 5.7+ / MariaDB 10.3+
  - Apache (mod_rewrite not required)
- Modern browser (Chrome, Firefox, Edge)

---

## 🚀 Setup Steps

### Step 1 – Copy project files
Place the `attendance_tracker/` folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\attendance_tracker\
```

### Step 2 – Start XAMPP services
Start **Apache** and **MySQL** from the XAMPP Control Panel.

### Step 3 – Import the database
1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **Import** → Choose `database.sql` → Click **Go**
3. A new database `attendance_tracker` will be created with tables and sample data.

### Step 4 – Configure database connection
Edit `includes/config.php` if your MySQL credentials differ from the defaults:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // Change if needed
define('DB_PASS', '');       // Change if needed
define('DB_NAME', 'attendance_tracker');
```

### Step 5 – Fix sample passwords (one-time)
Visit this URL in your browser:
```
http://localhost/attendance_tracker/setup_passwords.php
```
This sets correct bcrypt hashes for all sample accounts.
**⚠️ Delete `setup_passwords.php` after running it.**

### Step 6 – Open the application
```
http://localhost/attendance_tracker/
```

---

## 🔑 Demo Login Credentials

| Role     | Email                                    | Password    |
|----------|------------------------------------------|-------------|
| Admin    | admin@company.com                        | Admin@1234  |
| Employee | opokubekoenadia@gmail.com                | Pass@1234   |
| Employee | kitos0246@gmail.com                      | Pass@1234   |
| Employee | odaiclaudia2005@gmail.com                | Pass@1234   |
| Employee | antwiyawgyimah19@gmail.com               | Pass@1234   |
| Employee | owusukwabenarichond9@gmail.com           | Pass@1234   |

---

## ✨ Features Summary

### Employee Portal
- **Dashboard** – Live clock, check-in/out buttons, weekly hours chart, KPI radar chart
- **Attendance** – Monthly history with filter, CSV export
- **Leave Requests** – Apply for leave, track approval status, history
- **Performance** – View KPI scores with progress bars and trend chart
- **Notifications** – In-app alerts for leave decisions, score updates

### Admin Panel
- **Dashboard** – Company-wide stats, today's check-ins, pending leaves, top performers
- **Employees** – Add, edit, activate/deactivate employees
- **Attendance Overview** – Daily view (who's in/out) & monthly summary per employee
- **Leave Management** – Approve or reject with notes (auto-marks attendance as on_leave)
- **KPI Management** – Create KPIs, score employees by period, export results
- **Reports** – Monthly summary report with charts, printable, 3× CSV exports

### Security
- Passwords hashed with `bcrypt` via PHP `password_hash()`
- Role-based access control (admin vs employee routes)
- Session regeneration to prevent fixation
- Prepared statements throughout (SQL injection prevention)
- XSS prevention via `htmlspecialchars()` on all output

---

## 🛠 Customization

### Change work hours / late threshold
Edit `includes/config.php`:
```php
define('WORK_START',      '08:00');
define('LATE_THRESHOLD',  '09:00');
define('HALF_DAY_HOURS',   4.0);
define('FULL_DAY_HOURS',   8.0);
```

### Change timezone
```php
date_default_timezone_set('Africa/Accra'); // or 'America/New_York', 'Europe/London', etc.
```

### Add a department
Simply type the department name when adding/editing an employee – departments are free-text.

### Change app name
```php
define('APP_NAME', 'Your Company Tracker');
```

---

## 📊 Database Tables

| Table               | Description                          |
|---------------------|--------------------------------------|
| `employees`         | All user accounts (admin + employee) |
| `attendance`        | Daily check-in/out records           |
| `leave_requests`    | Leave applications + approval status |
| `performance_kpis`  | KPI definitions                      |
| `performance_scores`| KPI scores per employee per month    |
| `notifications`     | In-app notification messages         |

---

## � Production Checklist (before going live)
- [ ] Change DB credentials in `config.php`
- [ ] Set strong admin password
- [ ] Enable HTTPS and set `'secure' => true` in session config
- [ ] Set `APP_URL` to your actual domain
- [ ] Delete `setup_passwords.php`
- [ ] Remove or protect `database.sql` from public access
- [ ] Consider adding `.htaccess` to restrict `includes/` and `api/` to PHP only

---

*Built with PHP 8.1, MySQL, HTML/CSS/JS, Chart.js · No composer dependencies required*
