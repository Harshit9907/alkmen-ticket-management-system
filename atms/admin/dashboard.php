<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['admin']);

$total = (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
$open = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
$progress = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'in_progress'")->fetchColumn();
$resolved = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'")->fetchColumn();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-4">
    <div class="card stat-card"><h3>Total</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>In Progress</h3><p><?= $progress ?></p></div>
    <div class="card stat-card"><h3>Resolved</h3><p><?= $resolved ?></p></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
