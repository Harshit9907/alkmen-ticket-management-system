# Alkmen Ticket Management System (ATMS)

A minimal SaaS-style ticket management system built with core PHP and MySQL.

## Stack
- Core PHP (no framework)
- MySQL with PDO prepared statements
- HTML, CSS, JavaScript

## Project Structure
- `atms/config/` → application configuration (including DB bootstrap + migrations)
- `atms/database/migrations/` → ordered versioned SQL migrations (`*.sql`)
- `atms/admin/`, `atms/client/`, `atms/auth/` → role-based modules

## Exact XAMPP Setup (Windows)
1. Install XAMPP and start **Apache** + **MySQL** from XAMPP Control Panel.
2. Copy this repo to:
   - `C:\xampp\htdocs\alkmen-ticket-management-system`
3. Open:
   - `C:\xampp\php\php.ini`
   - Confirm `extension=pdo_mysql` is enabled (remove leading `;` if present).
4. (Optional) Create DB user in phpMyAdmin if not using default root user.
5. Update DB credentials in `atms/config/db.php` if needed:
   - `$host`, `$dbname`, `$username`, `$password`
6. Open app URL:
   - `http://localhost/alkmen-ticket-management-system/atms/index.php`

## Migration Flow (Versioned)
ATMS now runs versioned SQL migrations automatically at startup.

### What happens on startup
1. App connects to MySQL.
2. If DB `atms` is missing, app creates it.
3. App ensures `schema_migrations` table exists.
4. App scans `atms/database/migrations/*.sql` in lexical order.
5. Each not-yet-successful migration is executed and logged.

### Migration log table
`schema_migrations` tracks:
- `version` (migration filename, PK)
- `status` (`success` / `failed`)
- `executed_at`
- `error_message`

### Add a new migration
1. Create next file in `atms/database/migrations/`:
   - Example: `003_add_ticket_indexes.sql`
2. Put SQL in that file.
3. Refresh the app once; migration will run automatically.
4. Verify from phpMyAdmin:
   ```sql
   SELECT * FROM schema_migrations ORDER BY executed_at DESC;
   ```

## Startup Failure Messages
Startup now gives explicit failure category:
- **Credentials issue** → wrong host/user/password
- **Permission issue** → DB user lacks required privileges
- **Migration issue** → SQL in migration failed

## Default Accounts
- **Admin**: `admin@alkmen.com` / `admin123`
- **Clients**:
  - `john.client@alkmen.com` / `admin123`
  - `maya.client@alkmen.com` / `admin123`
