<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';
requireRole(['super_admin', 'admin']);

$role = (string) $_SESSION['role'];

$total = runScopedCount($pdo, $role, $_SESSION);
$open = runScopedCount($pdo, $role, $_SESSION, "t.status IN ('open', 'in_progress')");
$resolved = runScopedCount($pdo, $role, $_SESSION, "t.status = 'resolved'");

$recentTickets = $pdo->query(
    "SELECT t.ticket_id, t.subject, t.status, t.priority, t.created_at, owner.name AS requester_name
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     ORDER BY t.created_at DESC
     LIMIT 8"
)->fetchAll();

$highPriority = $pdo->query(
    "SELECT t.ticket_id, t.subject, t.status, t.created_at, owner.name AS requester_name
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     WHERE t.priority = 'high' AND t.status IN ('open', 'in_progress')
     ORDER BY t.created_at ASC
     LIMIT 8"
)->fetchAll();

$pageTitle = 'Super Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>All Company Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open/In Progress</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Resolved</h3><p><?= $resolved ?></p></div>
</div>

<div class="card mt-16">
    <h2>Recent Tickets</h2>
    <table data-sortable>
        <thead><tr><th>Ticket</th><th>Requester</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($recentTickets as $ticket): ?>
            <tr>
                <td><?= e($ticket['ticket_id']) ?></td>
                <td><?= e($ticket['requester_name']) ?></td>
                <td><?= e($ticket['subject']) ?></td>
                <td><span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></td>
                <td><?= e(date('M d, Y h:i A', strtotime($ticket['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card mt-16">
    <h2>High-Priority Queue</h2>
    <table data-sortable>
        <thead><tr><th>Ticket</th><th>Requester</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (!$highPriority): ?>
            <tr><td colspan="5" class="muted">No high-priority queue currently.</td></tr>
        <?php else: ?>
            <?php foreach ($highPriority as $ticket): ?>
                <tr>
                    <td><?= e($ticket['ticket_id']) ?></td>
                    <td><?= e($ticket['requester_name']) ?></td>
                    <td><?= e($ticket['subject']) ?></td>
                    <td><span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($ticket['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
