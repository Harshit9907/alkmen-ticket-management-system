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
    $isMissingDb = $errorCode === 1049 || str_contains($message, 'Unknown database') || str_contains($message, '[1049]');

    if (!$isMissingDb) {
        die('Database connection failed: ' . $exception->getMessage());
    }

    try {
        $serverPdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, $pdoOptions);
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

function canManageTickets(): bool
{
    return in_array($_SESSION['role'] ?? '', ['client_admin', 'super_admin'], true);
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
