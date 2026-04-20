-- Adds multi-tenant company model and backfills legacy company_name data.
USE atms;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO companies (name, status)
VALUES ('Default Company', 'active')
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    updated_at = CURRENT_TIMESTAMP;

SET @users_has_company_name := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND column_name = 'company_name'
);

SET @tickets_has_company_name := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'tickets'
      AND column_name = 'company_name'
);

SET @sql := IF(
    @users_has_company_name > 0,
    'INSERT INTO companies (name, status)
     SELECT DISTINCT TRIM(company_name), ''active''
     FROM users
     WHERE company_name IS NOT NULL AND TRIM(company_name) <> ''''
     ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @tickets_has_company_name > 0,
    'INSERT INTO companies (name, status)
     SELECT DISTINCT TRIM(company_name), ''active''
     FROM tickets
     WHERE company_name IS NOT NULL AND TRIM(company_name) <> ''''
     ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL;

UPDATE users u
LEFT JOIN companies c ON c.name = COALESCE(NULLIF(TRIM(u.company_name), ''), 'Default Company')
SET u.company_id = c.id
WHERE u.company_id IS NULL
  AND @users_has_company_name > 0;

UPDATE users
SET company_id = (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1)
WHERE company_id IS NULL;

ALTER TABLE users
    MODIFY company_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS company_id INT UNSIGNED NULL;

UPDATE tickets t
JOIN users u ON u.id = t.user_id
SET t.company_id = u.company_id
WHERE t.company_id IS NULL;

UPDATE tickets t
LEFT JOIN companies c ON c.name = COALESCE(NULLIF(TRIM(t.company_name), ''), 'Default Company')
SET t.company_id = c.id
WHERE t.company_id IS NULL
  AND @tickets_has_company_name > 0;

UPDATE tickets
SET company_id = (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1)
WHERE company_id IS NULL;

ALTER TABLE tickets
    MODIFY company_id INT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_tickets_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;

CREATE INDEX idx_users_company_id ON users (company_id);
CREATE INDEX idx_tickets_company_id ON tickets (company_id);
