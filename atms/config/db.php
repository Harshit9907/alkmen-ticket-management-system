<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'atms';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    die('Database connection failed: ' . $exception->getMessage());
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
