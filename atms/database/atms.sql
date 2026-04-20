CREATE DATABASE IF NOT EXISTS atms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atms;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(80) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    scope_type ENUM('global', 'company') NOT NULL,
    company_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(20) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(120) NOT NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
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

INSERT INTO roles (role_key, display_name, scope_type, company_id) VALUES
('super_admin', 'Super Admin', 'global', NULL),
('client_admin', 'Client Admin', 'company', NULL),
('manager', 'Manager', 'company', NULL),
('employee', 'Employee', 'company', NULL)
ON DUPLICATE KEY UPDATE
display_name = VALUES(display_name),
scope_type = VALUES(scope_type),
company_id = VALUES(company_id);

INSERT INTO permissions (permission_key, description) VALUES
('can_raise_ticket', 'Can create a new ticket'),
('can_view_own_tickets', 'Can view own tickets'),
('can_view_team_tickets', 'Can view team tickets'),
('can_view_company_tickets', 'Can view all company tickets'),
('can_view_reports', 'Can view reports and analytics'),
('can_manage_users', 'Can manage users'),
('can_manage_roles', 'Can manage role assignments')
ON DUPLICATE KEY UPDATE description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE
    (r.role_key = 'super_admin')
    OR (r.role_key = 'client_admin' AND p.permission_key IN (
        'can_raise_ticket',
        'can_view_own_tickets',
        'can_view_team_tickets',
        'can_view_company_tickets',
        'can_view_reports',
        'can_manage_users',
        'can_manage_roles'
    ))
    OR (r.role_key = 'manager' AND p.permission_key IN (
        'can_raise_ticket',
        'can_view_own_tickets',
        'can_view_team_tickets',
        'can_view_reports'
    ))
    OR (r.role_key = 'employee' AND p.permission_key IN (
        'can_raise_ticket',
        'can_view_own_tickets'
    ))
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

INSERT INTO users (name, email, password, role) VALUES
('ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin'),
('John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client'),
('Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client')
ON DUPLICATE KEY UPDATE email = VALUES(email);

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
JOIN roles r ON (
    (u.role = 'admin' AND r.role_key = 'super_admin')
    OR (u.role = 'client' AND r.role_key = 'employee')
)
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id);

INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1001', u.id, 'Unable to login on mobile', 'Client cannot login from mobile app and gets session timeout.', 'Technical', 'high', 'open', a.id, NOW() - INTERVAL 2 DAY
FROM users u CROSS JOIN users a
WHERE u.email = 'john.client@alkmen.com' AND a.email = 'admin@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1001');

INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status, assigned_to, created_at)
SELECT 'ALK-1002', u.id, 'Invoice mismatch for March', 'Invoice amount does not match approved estimate.', 'Billing', 'medium', 'in_progress', a.id, NOW() - INTERVAL 1 DAY
FROM users u CROSS JOIN users a
WHERE u.email = 'maya.client@alkmen.com' AND a.email = 'admin@alkmen.com'
AND NOT EXISTS (SELECT 1 FROM tickets WHERE ticket_id = 'ALK-1002');

INSERT INTO messages (ticket_id, sender_id, message, file, created_at)
SELECT t.id, u.id, 'I am unable to sign in from my phone since yesterday.', NULL, NOW() - INTERVAL 2 DAY
FROM tickets t JOIN users u ON u.email = 'john.client@alkmen.com'
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'I am unable to sign in from my phone since yesterday.');

INSERT INTO messages (ticket_id, sender_id, message, file, created_at)
SELECT t.id, a.id, 'We are checking the mobile auth logs now.', NULL, NOW() - INTERVAL 1 DAY
FROM tickets t JOIN users a ON a.email = 'admin@alkmen.com'
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'We are checking the mobile auth logs now.');
