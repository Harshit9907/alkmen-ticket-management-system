<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'atms';
$username = 'root';
$password = '';

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

final class MigrationException extends RuntimeException
{
}

/**
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
function bootstrapDatabase(PDO $serverPdo, string $databaseName): void
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);

    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;
    $compoundDepth = 0;
    $currentToken = '';

    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $index++;
                $inBlockComment = false;
            }
            continue;
        }

        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            if ($char === '-' && $next === '-') {
                $buffer .= $char . $next;
                $index++;
                $inLineComment = true;
                continue;
            }
            if ($char === '#') {
                $buffer .= $char;
                $inLineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $buffer .= $char . $next;
                $index++;
                $inBlockComment = true;
                continue;
            }
        }

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement !== '') {
            $serverPdo->exec($statement);
        }
    }
}

function defaultRolePermissions(string $slug): array
{
    return match ($slug) {
        'admin' => ['tickets.view_all', 'tickets.manage', 'roles.manage', 'users.manage'],
        'client' => ['tickets.raise', 'tickets.view_own'],
        default => [],
    };
}

function ensureRbacSchema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        is_protected TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS role_permissions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        role_id INT UNSIGNED NOT NULL,
        permission_key VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_role_permission (role_id, permission_key),
        CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role_id'");
    if (!$columnStmt->fetch()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN role_id INT UNSIGNED NULL AFTER role');
        $pdo->exec('ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL');
    }

    $seed = $pdo->prepare('INSERT INTO roles (name, slug, is_protected) VALUES (:name, :slug, 1)
                           ON DUPLICATE KEY UPDATE name = VALUES(name), is_protected = 1');
    foreach (['admin' => 'Administrator', 'client' => 'Client'] as $slug => $name) {
        $seed->execute(['name' => $name, 'slug' => $slug]);

        $roleIdStmt = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $roleIdStmt->execute(['slug' => $slug]);
        $roleId = (int) $roleIdStmt->fetchColumn();

        foreach (defaultRolePermissions($slug) as $permission) {
            $permStmt = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_key) VALUES (:role_id, :permission_key)');
            $permStmt->execute(['role_id' => $roleId, 'permission_key' => $permission]);
        }

        $syncUsers = $pdo->prepare('UPDATE users u JOIN roles r ON r.slug = u.role SET u.role_id = r.id WHERE u.role = :slug AND (u.role_id IS NULL OR u.role_id = 0)');
        $syncUsers->execute(['slug' => $slug]);
        if (!$inDoubleQuote && !$inBacktick && $char === '\'' && ($index === 0 || $sql[$index - 1] !== '\\')) {
            $inSingleQuote = !$inSingleQuote;
        } elseif (!$inSingleQuote && !$inBacktick && $char === '"' && ($index === 0 || $sql[$index - 1] !== '\\')) {
            $inDoubleQuote = !$inDoubleQuote;
        } elseif (!$inSingleQuote && !$inDoubleQuote && $char === '`') {
            $inBacktick = !$inBacktick;
        }

        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick && ctype_alpha($char)) {
            $currentToken .= strtoupper($char);
        } else {
            if ($currentToken !== '') {
                if ($currentToken === 'BEGIN') {
                    $compoundDepth++;
                } elseif ($currentToken === 'END' && $compoundDepth > 0) {
                    $compoundDepth--;
                }
                $currentToken = '';
            }
        }

        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick && $compoundDepth === 0 && $char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $serverPdo->exec($statement);
        }
    }
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin', 'client_admin', 'manager', 'employee', 'admin', 'client') NOT NULL DEFAULT 'employee'");

    $columns = [
        'company_id' => 'ALTER TABLE users ADD COLUMN company_id INT UNSIGNED NULL AFTER role',
        'manager_id' => 'ALTER TABLE users ADD COLUMN manager_id INT UNSIGNED NULL AFTER company_id',
    ];

    foreach ($columns as $name => $sql) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $check->execute([
            'table_name' => 'users',
            'column_name' => $name,
        ]);

        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function ensureDatabaseExists(PDO $serverPdo, string $databaseName): void
{
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function ensureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(255) PRIMARY KEY,
            status ENUM('success', 'failed') NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            error_message TEXT NULL
        )
        SQL
    );
}

/**
 * @return list<string>
 */
function discoverMigrations(string $directory): array
{
    if (!is_dir($directory)) {
        throw new MigrationException('Migration directory not found: ' . $directory);
    }

    $files = glob($directory . '/*.sql');
    if ($files === false) {
        throw new MigrationException('Unable to read migration files from: ' . $directory);
try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, $pdoOptions);
} catch (PDOException $exception) {
    $errorCode = (int) ($exception->errorInfo[1] ?? 0);
    $message = $exception->getMessage();
    $isMissingDb = $errorCode === 1049 || str_contains($message, 'Unknown database') || str_contains($message, '[1049]');
    $isMissingDb = $errorCode === 1049
        || str_contains($message, 'Unknown database')
        || str_contains($message, '[1049]');
    $isMissingDb = $errorCode === 1049 || str_contains($message, 'Unknown database') || str_contains($message, '[1049]');
    $isMissingDb = $errorCode === 1049 || str_contains($exception->getMessage(), 'Unknown database');

    if (!$isMissingDb) {
        die('Database connection failed: ' . $exception->getMessage());
    }

    sort($files, SORT_STRING);

    return array_values($files);
}

function hasSuccessfulMigration(PDO $pdo, string $version): bool
{
    $stmt = $pdo->prepare('SELECT status FROM schema_migrations WHERE version = :version LIMIT 1');
    $stmt->execute(['version' => $version]);
    $result = $stmt->fetchColumn();

    return $result === 'success';
}

function markMigrationStatus(PDO $pdo, string $version, string $status, ?string $errorMessage = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO schema_migrations (version, status, error_message) VALUES (:version, :status, :error_message)
        ON DUPLICATE KEY UPDATE status = VALUES(status), executed_at = CURRENT_TIMESTAMP, error_message = VALUES(error_message)'
    );
    $stmt->execute([
        'version' => $version,
        'status' => $status,
        'error_message' => $errorMessage,
    ]);
}

function runMigrations(PDO $pdo, string $migrationsDirectory): void
{
    ensureMigrationsTable($pdo);
    $migrationFiles = discoverMigrations($migrationsDirectory);

    foreach ($migrationFiles as $migrationFile) {
        $version = basename($migrationFile);
        if (hasSuccessfulMigration($pdo, $version)) {
            continue;
        }

        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            throw new MigrationException("Unable to read migration file: {$version}");
        }

        $statements = splitSqlStatements($sql);

        try {
            $pdo->beginTransaction();
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
            markMigrationStatus($pdo, $version, 'success');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            markMigrationStatus($pdo, $version, 'failed', $exception->getMessage());
            throw new MigrationException("Migration failed for {$version}: {$exception->getMessage()}", 0, $exception);
        }
    }
}

function connectWithBootstrap(string $host, string $dbname, string $username, string $password, array $pdoOptions): PDO
{
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $username,
            $password,
            $pdoOptions
        );
    } catch (PDOException $exception) {
        $errorCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = $exception->getMessage();

        $isCredentialIssue = in_array($errorCode, [1045, 1698], true)
            || str_contains($message, 'Access denied for user');
        if ($isCredentialIssue) {
            die('Database credentials issue: unable to authenticate with MySQL. Please verify host, username, and password in atms/config/db.php.');
        }

        $isMissingDatabase = $errorCode === 1049 || str_contains($message, 'Unknown database');
        if (!$isMissingDatabase) {
            $isPermissionIssue = in_array($errorCode, [1044, 1142, 1227], true)
                || str_contains($message, 'command denied')
                || str_contains($message, 'permission denied');
            if ($isPermissionIssue) {
                die('Database permission issue: current MySQL user does not have sufficient privileges to access the database.');
            }
            die('Database connection failed: ' . $message);
        }

        try {
            $serverPdo = new PDO(
                "mysql:host={$host};charset=utf8mb4",
                $username,
                $password,
                $pdoOptions
            );
            ensureDatabaseExists($serverPdo, $dbname);
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                $pdoOptions
            );
        } catch (PDOException $bootstrapException) {
            $bootstrapCode = (int) ($bootstrapException->errorInfo[1] ?? 0);
            if (in_array($bootstrapCode, [1044, 1142, 1227], true)) {
                die('Database permission issue: unable to create or modify the database with current MySQL privileges.');
            }
            die('Database bootstrap failed: ' . $bootstrapException->getMessage());
        }
    }

    $migrationsDirectory = __DIR__ . '/../database/migrations';

    try {
        runMigrations($pdo, $migrationsDirectory);
    } catch (MigrationException $exception) {
        die('Database migration issue: ' . $exception->getMessage());
    }

    return $pdo;
}

$pdo = connectWithBootstrap($host, $dbname, $username, $password, $pdoOptions);
        $serverPdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, $pdoOptions);
        bootstrapDatabase($serverPdo, $dbname);
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, $pdoOptions);
    } catch (PDOException $bootstrapException) {
        die('Database bootstrap failed: ' . $bootstrapException->getMessage());
    }
}

ensureRbacSchema($pdo);
ensureSchema($pdo);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function refreshSessionAuth(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('SELECT u.id, u.name, u.role, r.slug AS role_slug
                           FROM users u
                           LEFT JOIN roles r ON r.id = u.role_id
                           WHERE u.id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    $roleIdStmt = $pdo->prepare('SELECT role_id FROM users WHERE id = :id LIMIT 1');
    $roleIdStmt->execute(['id' => $userId]);
    $roleId = (int) $roleIdStmt->fetchColumn();

    $permStmt = $pdo->prepare('SELECT permission_key FROM role_permissions WHERE role_id = :role_id');
    $permStmt->execute(['role_id' => $roleId]);
    $permissions = array_map(static fn (array $row): string => $row['permission_key'], $permStmt->fetchAll());

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['role_slug'] = $user['role_slug'] ?? $user['role'];
    $_SESSION['permissions'] = $permissions;
function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function currentCompanyId(): ?int
{
    $companyId = $_SESSION['company_id'] ?? null;
    return $companyId === null ? null : (int) $companyId;
}

function requireRole(array $roles): void
{
    if (!isLoggedIn() || !in_array($_SESSION['role'], $roles, true)) {
        redirect('/atms/index.php');
    }
}

function hasPermission(string $permission): bool
{
    return in_array($permission, $_SESSION['permissions'] ?? [], true);
}

function requirePermission(string $permission): void
{
    if (!isLoggedIn() || !hasPermission($permission)) {
        redirect('/atms/index.php');
    }
}


function permissionCatalog(): array
{
    return [
        'tickets.view_all' => 'View all tickets',
        'tickets.manage' => 'Manage ticket status and assignments',
        'tickets.raise' => 'Raise ticket',
        'tickets.view_own' => 'View own tickets',
        'users.manage' => 'Manage users',
        'roles.manage' => 'Manage roles',
    ];
function canManageTickets(): bool
{
    return in_array($_SESSION['role'] ?? '', ['client_admin', 'super_admin'], true);
function canManageTicketActions(): bool
{
    return (($_SESSION['role'] ?? '') === 'super_admin');
}

function canClientRaiseOrReply(string $role): bool
{
    $explicitAllowedRoles = ['client_plus', 'client_support'];

    return in_array($role, $explicitAllowedRoles, true);
}

function generateTicketId(PDO $pdo): string
{
    do {
        $number = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $ticketId = 'ALK-' . $number;
        $stmt = $pdo->prepare('SELECT id FROM tickets WHERE ticket_id = :ticket_id LIMIT 1');
        $stmt->execute(['ticket_id' => $ticketId]);
    } while ($stmt->fetch());

    return $ticketId;
}

function badgeClass(string $status): string
{
    return match ($status) {
        'open' => 'badge badge-open',
        'in_progress' => 'badge badge-progress',
        'resolved' => 'badge badge-resolved',
        default => 'badge',
    };
}

function priorityClass(string $priority): string
{
    return match ($priority) {
        'high' => 'priority priority-high',
        'medium' => 'priority priority-medium',
        default => 'priority priority-low',
    };
}

function allowedUploadExtension(string $fileName): bool
{
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'doc', 'docx'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    return in_array($extension, $allowed, true);
}

function uploadFile(array $file): ?string
{
    if (!isset($file['name'], $file['tmp_name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!allowedUploadExtension($file['name'])) {
        return null;
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newName = uniqid('atms_', true) . '.' . $extension;
    $destination = __DIR__ . '/../assets/uploads/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    return $newName;
}

function logTicketEvent(PDO $pdo, int $ticketId, string $eventType, ?string $oldValue, ?string $newValue, int $actorId): void
{
    $stmt = $pdo->prepare('INSERT INTO ticket_events (ticket_id, event_type, old_value, new_value, actor_id) VALUES (:ticket_id, :event_type, :old_value, :new_value, :actor_id)');
    $stmt->execute([
        'ticket_id' => $ticketId,
        'event_type' => $eventType,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'actor_id' => $actorId,
    ]);
}
