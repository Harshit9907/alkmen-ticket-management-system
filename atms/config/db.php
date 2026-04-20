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

function bootstrapDatabase(PDO $serverPdo, string $databaseName): void
{
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $serverPdo->exec("USE `{$databaseName}`");

    $sqlFile = __DIR__ . '/../database/atms.sql';
    if (!is_file($sqlFile)) {
        return;
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        return;
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
    }
}

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
    $isMissingDb = $errorCode === 1049 || str_contains($message, 'Unknown database') || str_contains($message, '[1049]');

    if (!$isMissingDb) {
        die('Database connection failed: ' . $exception->getMessage());
    }

    try {
        $serverPdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, $pdoOptions);
        bootstrapDatabase($serverPdo, $dbname);
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, $pdoOptions);
    } catch (PDOException $bootstrapException) {
        die('Database bootstrap failed: ' . $bootstrapException->getMessage());
    }
}

ensureRbacSchema($pdo);

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
