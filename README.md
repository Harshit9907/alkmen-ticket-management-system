# Alkmen Ticket Management System (ATMS)

A production-ready, minimal SaaS-style ticket management system built with core PHP and MySQL.

## Stack
- Core PHP (no framework)
- MySQL via PDO prepared statements
- HTML, CSS, JavaScript

## Local Setup (XAMPP / MAMP)
1. Copy this repository to your web root (e.g., `htdocs`).
2. Import `atms/database/atms.sql` into MySQL.
3. Confirm DB credentials in `atms/config/db.php`.
4. Open `http://localhost/atms/index.php`.

## Default Accounts
- **Admin**: `admin@alkmen.com` / `admin123`
- **Sample Clients**:
  - `john.client@alkmen.com` / `admin123`
  - `maya.client@alkmen.com` / `admin123`

## Included Seed Data
- Default admin + sample clients
- Sample tickets and messages
- Ready-to-use dashboard metrics and ticket history
A minimal, production-ready ticket management application built with core PHP, MySQL, HTML/CSS, and JavaScript.

## Stack
- Core PHP (no framework)
- MySQL (PDO prepared statements)
- HTML/CSS/JS

## Project Path
Application code lives in `atms/` with modular directories for config, auth, client, admin, includes, assets, and database.

## Setup (XAMPP / MAMP)
1. Place this repository in your web root (e.g. `htdocs`).
2. Create database and tables:
   - Import `atms/database/atms.sql` in phpMyAdmin, or run via CLI.
3. Update DB credentials in `atms/config/db.php` if needed.
4. Open: `http://localhost/atms/index.php`.

## Default admin
- Email: `admin@atms.local`
- Password: `Admin@123`

> Password hash is pre-seeded in SQL for quick local startup.
