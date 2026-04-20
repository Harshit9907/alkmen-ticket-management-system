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

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $serverPdo->exec($statement);
        }
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
    $isMissingDb = $errorCode === 1049
        || str_contains($message, 'Unknown database')
        || str_contains($message, '[1049]');

    if (!$isMissingDb) {
        die('Database connection failed: ' . $exception->getMessage());
    }

    try {
        $serverPdo = new PDO(
            "mysql:host={$host};charset=utf8mb4",
            $username,
            $password,
            $pdoOptions
        );
        bootstrapDatabase($serverPdo, $dbname);
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $username,
            $password,
            $pdoOptions
        );
    } catch (PDOException $bootstrapException) {
        die('Database bootstrap failed: ' . $bootstrapException->getMessage());
    }
}

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

function requireRole(array $roles): void
{
    if (!isLoggedIn() || !in_array($_SESSION['role'], $roles, true)) {
        redirect('/atms/index.php');
    }
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

/**
 * Scope strategy:
 * - client: can access own tickets only
 * - manager: can access tickets of mapped users in manager_client_map
 * - admin/client_admin: can access all client tickets
 */
function getScopedClientIds(PDO $pdo, int $sessionUserId, string $sessionRole): array
{
    if ($sessionRole === 'client') {
        return [$sessionUserId];
    }

    if (in_array($sessionRole, ['admin', 'client_admin'], true)) {
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'client'");

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    if ($sessionRole === 'manager') {
        $stmt = $pdo->prepare('SELECT user_id FROM manager_client_map WHERE manager_id = :manager_id');
        $stmt->execute(['manager_id' => $sessionUserId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    return [];
}

function buildTicketScopeFilter(PDO $pdo, int $sessionUserId, string $sessionRole, string $ticketUserField = 't.user_id'): array
{
    $clientIds = getScopedClientIds($pdo, $sessionUserId, $sessionRole);
    if ($clientIds === []) {
        return ['sql' => '1 = 0', 'params' => []];
    }

    $placeholders = [];
    $params = [];
    foreach ($clientIds as $i => $clientId) {
        $key = 'scope_' . $i;
        $placeholders[] = ':' . $key;
        $params[$key] = $clientId;
    }

    return [
        'sql' => sprintf('%s IN (%s)', $ticketUserField, implode(', ', $placeholders)),
        'params' => $params,
    ];
}

function canAccessTicket(PDO $pdo, int $ticketPk, int $sessionUserId, string $sessionRole): bool
{
    $scope = buildTicketScopeFilter($pdo, $sessionUserId, $sessionRole, 't.user_id');
    $query = sprintf('SELECT 1 FROM tickets t WHERE t.id = :ticket_id AND %s LIMIT 1', $scope['sql']);
    $params = array_merge(['ticket_id' => $ticketPk], $scope['params']);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}
