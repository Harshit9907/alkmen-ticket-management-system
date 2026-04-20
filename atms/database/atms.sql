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

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    company_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
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

INSERT INTO messages (ticket_id, sender_id, message, file, created_at)
SELECT t.id, a.id, 'We are checking the mobile auth logs now.', NULL, NOW() - INTERVAL 1 DAY
FROM tickets t
JOIN users a ON a.email = 'admin@alkmen.com' AND a.company_id = t.company_id
WHERE t.ticket_id = 'ALK-1001'
AND NOT EXISTS (SELECT 1 FROM messages m WHERE m.ticket_id = t.id AND m.message = 'We are checking the mobile auth logs now.');
