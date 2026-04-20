INSERT INTO users (name, email, password, role) VALUES
('ATMS Admin', 'admin@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'admin'),
('John Client', 'john.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client'),
('Maya Client', 'maya.client@alkmen.com', '$2y$12$lJg9PR/bbVZumMGrPA6SxeKvrifyIFVVD/ivMznIb69vOnDD.EQr2', 'client')
ON DUPLICATE KEY UPDATE email = VALUES(email);

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
