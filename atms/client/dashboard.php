<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);

$userId = (int) $_SESSION['user_id'];

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE user_id = :user_id');
$totalStmt->execute(['user_id' => $userId]);
$total = (int) $totalStmt->fetchColumn();

$openStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status = 'open'");
$openStmt->execute(['user_id' => $userId]);
$open = (int) $openStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status = 'resolved'");
$resolvedStmt->execute(['user_id' => $userId]);
$resolved = (int) $resolvedStmt->fetchColumn();

$pageTitle = 'Client Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Total Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Resolved</h3><p><?= $resolved ?></p></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
