# Alkmen Ticket Management System (ATMS)

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
