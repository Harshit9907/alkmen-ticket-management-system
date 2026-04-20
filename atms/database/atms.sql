CREATE DATABASE IF NOT EXISTS atms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atms;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO companies (name, status)
VALUES ('Alkmen Default', 'active')
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    updated_at = CURRENT_TIMESTAMP;
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    is_protected TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    code VARCHAR(32) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    key_name VARCHAR(80) NOT NULL,
    label VARCHAR(120) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_role_company_key (company_id, key_name)
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(160) NOT NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    company_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
    role_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_key VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_role_permission (role_id, permission_key),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    role ENUM('admin', 'client_admin', 'manager', 'client') NOT NULL DEFAULT 'client',
    role ENUM('super_admin', 'client_admin', 'manager', 'employee', 'admin', 'client') NOT NULL DEFAULT 'employee',
    company_id INT UNSIGNED NULL,
    manager_id INT UNSIGNED NULL,
    role ENUM('super_admin', 'client_admin', 'client') NOT NULL DEFAULT 'client',
    must_reset_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    email VARCHAR(160) NOT NULL,
    role ENUM('client', 'client_admin') NOT NULL DEFAULT 'client',
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invitations_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_invitations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    role ENUM('super_admin', 'admin', 'client', 'client_plus', 'client_support') NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS manager_client_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manager_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_manager_user (manager_id, user_id),
    CONSTRAINT fk_map_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_map_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(20) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(120) NOT NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    sla_deadline DATETIME NULL,
    is_overdue TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tickets_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_admin FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password, role, company_id)
SELECT 'ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin', c.id
FROM companies c
WHERE c.name = 'Alkmen Default'
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO users (name, email, password, role, company_id)
SELECT 'John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client', c.id
FROM companies c
WHERE c.name = 'Alkmen Default'
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO users (name, email, password, role, company_id)
SELECT 'Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client', c.id
FROM companies c
WHERE c.name = 'Alkmen Default'
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO tickets (ticket_id, user_id, company_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1001', u.id, u.company_id, 'Unable to login on mobile', 'Client cannot login from mobile app and gets session timeout.', 'Technical', 'high', 'open', a.id, NOW() - INTERVAL 2 DAY
FROM users u
JOIN users a ON a.email = 'admin@alkmen.com' AND a.company_id = u.company_id
WHERE u.email = 'john.client@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1001');

INSERT INTO tickets (ticket_id, user_id, company_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1002', u.id, u.company_id, 'Invoice mismatch for March', 'Invoice amount does not match approved estimate.', 'Billing', 'medium', 'in_progress', a.id, NOW() - INTERVAL 1 DAY
FROM users u
JOIN users a ON a.email = 'admin@alkmen.com' AND a.company_id = u.company_id
WHERE u.email = 'maya.client@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1002');

INSERT INTO messages (ticket_id, sender_id, message, file, created_at)
SELECT t.id, u.id, 'I am unable to sign in from my phone since yesterday.', NULL, NOW() - INTERVAL 2 DAY
FROM tickets t
JOIN users u ON u.email = 'john.client@alkmen.com' AND u.company_id = t.company_id
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'I am unable to sign in from my phone since yesterday.');
INSERT INTO roles (name, slug, is_protected) VALUES
('Administrator', 'admin', 1),
('Client', 'client', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_protected = VALUES(is_protected);

INSERT IGNORE INTO role_permissions (role_id, permission_key)
SELECT r.id, p.permission_key FROM roles r
JOIN (
    SELECT 'admin' AS slug, 'tickets.view_all' AS permission_key
    UNION ALL SELECT 'admin', 'tickets.manage'
    UNION ALL SELECT 'admin', 'roles.manage'
    UNION ALL SELECT 'admin', 'users.manage'
    UNION ALL SELECT 'client', 'tickets.raise'
    UNION ALL SELECT 'client', 'tickets.view_own'
) p ON p.slug = r.slug;

INSERT INTO users (name, email, password, role, role_id)
SELECT 'ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin', r.id
FROM roles r
WHERE r.slug = 'admin'
ON DUPLICATE KEY UPDATE email = VALUES(email);
INSERT INTO users (name, email, password, role) VALUES
('ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin'),
('ATMS Client Admin', 'clientadmin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client_admin'),
('ATMS Manager', 'manager@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'manager'),
('John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client'),
('Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client')
ON DUPLICATE KEY UPDATE email = VALUES(email), role = VALUES(role), name = VALUES(name);

INSERT INTO manager_client_map (manager_id, user_id)
SELECT m.id, c.id
FROM users m
JOIN users c ON c.email IN ('john.client@alkmen.com', 'maya.client@alkmen.com')
WHERE m.email = 'manager@alkmen.com'
ON DUPLICATE KEY UPDATE manager_id = VALUES(manager_id);
INSERT INTO users (name, email, password, role, company_id, manager_id) VALUES
('ATMS Super Admin', 'superadmin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'super_admin', 1, NULL),
('Acme Client Admin', 'clientadmin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client_admin', 1, NULL),
('Acme Manager', 'manager@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'manager', 1, NULL),
('John Employee', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'employee', 1, NULL),
('Maya Employee', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'employee', 1, NULL)
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1001', u.id, 'Unable to login on mobile', 'Client cannot login from mobile app and gets session timeout.', 'Technical', 'high', 'open', a.id, NOW() - INTERVAL 2 DAY
FROM users u CROSS JOIN users a
WHERE u.email = 'john.client@alkmen.com' AND a.email = 'manager@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1001');

INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1002', u.id, 'Invoice mismatch for March', 'Invoice amount does not match approved estimate.', 'Billing', 'medium', 'in_progress', a.id, NOW() - INTERVAL 1 DAY
FROM users u CROSS JOIN users a
WHERE u.email = 'maya.client@alkmen.com' AND a.email = 'manager@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1002');

INSERT INTO users (name, email, password, role, role_id)
SELECT 'John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client', r.id
FROM roles r
WHERE r.slug = 'client'
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO users (name, email, password, role, role_id)
SELECT 'Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client', r.id
FROM roles r
WHERE r.slug = 'client'
INSERT INTO messages (ticket_id, sender_id, message, file, created_at)
SELECT t.id, a.id, 'We are checking the mobile auth logs now.', NULL, NOW() - INTERVAL 1 DAY
FROM tickets t
JOIN users a ON a.email = 'admin@alkmen.com' AND a.company_id = t.company_id
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'We are checking the mobile auth logs now.');
FROM tickets t JOIN users a ON a.email = 'manager@alkmen.com'
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'We are checking the mobile auth logs now.');
INSERT INTO permissions (key_name, label) VALUES
('tickets.view', 'View Tickets'),
('tickets.manage', 'Manage Tickets'),
('users.manage', 'Manage Users')
ON DUPLICATE KEY UPDATE label = VALUES(label);

INSERT INTO roles (company_id, key_name, label, is_system)
SELECT NULL, 'super_admin', 'Super Admin', 1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE company_id IS NULL AND key_name = 'super_admin');

INSERT INTO companies (name, code)
SELECT 'Demo Company', 'DEMO'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE code = 'DEMO');

INSERT INTO roles (company_id, key_name, label, is_system)
SELECT c.id, 'client_admin', 'Client Admin', 1 FROM companies c
WHERE c.code = 'DEMO' AND NOT EXISTS (
    SELECT 1 FROM roles r WHERE r.company_id = c.id AND r.key_name = 'client_admin'
);

INSERT INTO roles (company_id, key_name, label, is_system)
SELECT c.id, 'client_user', 'Client User', 1 FROM companies c
WHERE c.code = 'DEMO' AND NOT EXISTS (
    SELECT 1 FROM roles r WHERE r.company_id = c.id AND r.key_name = 'client_user'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name IN ('tickets.view', 'tickets.manage', 'users.manage')
WHERE r.key_name = 'super_admin'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name IN ('tickets.view', 'tickets.manage', 'users.manage')
JOIN companies c ON c.id = r.company_id
WHERE c.code = 'DEMO' AND r.key_name = 'client_admin'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.key_name = 'tickets.view'
JOIN companies c ON c.id = r.company_id
WHERE c.code = 'DEMO' AND r.key_name = 'client_user'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO users (company_id, name, email, password, role, must_reset_password)
SELECT NULL, 'ATMS Super Admin', 'superadmin@alkmen.com',
       '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2',
       'super_admin', 0
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'superadmin@alkmen.com');

INSERT INTO users (company_id, name, email, password, role, must_reset_password)
SELECT c.id, 'Demo Client Admin', 'clientadmin@demo.com',
       '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2',
       'client_admin', 0
FROM companies c
WHERE c.code = 'DEMO'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'clientadmin@demo.com');

INSERT INTO users (company_id, name, email, password, role, must_reset_password)
SELECT c.id, 'John Client', 'john.client@demo.com',
       '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2',
       'client', 0
FROM companies c
WHERE c.code = 'DEMO'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'john.client@demo.com');
CREATE TABLE IF NOT EXISTS ticket_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    event_type ENUM('status_change', 'assignment_change') NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    actor_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_events_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_events_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password, role) VALUES
('ATMS Super Admin', 'superadmin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'super_admin'),
('ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin'),
('John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client'),
('Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client')
ON DUPLICATE KEY UPDATE email = VALUES(email);

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_deadline DATETIME NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS is_overdue TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'client', 'client_plus', 'client_support') NOT NULL DEFAULT 'client';
