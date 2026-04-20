<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';

redirect(currentDashboardRoute((string) $_SESSION['role']));
requireRole(['client', 'client_plus', 'client_support']);

$userId = currentUserId();

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE user_id = :user_id');
$totalStmt->execute(['user_id' => $userId]);
$total = (int) $totalStmt->fetchColumn();

$openStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status = 'open'");
$openStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status IN ('open', 'in_progress')");
$openStmt->execute(['user_id' => $userId]);
$open = (int) $openStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :user_id AND status = 'resolved'");
$resolvedStmt->execute(['user_id' => $userId]);
$resolved = (int) $resolvedStmt->fetchColumn();

$recentStmt = $pdo->prepare('SELECT id, ticket_id, subject, status, priority, created_at FROM tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5');
$recentStmt->execute(['user_id' => $userId]);
$recentTickets = $recentStmt->fetchAll();

$pageTitle = 'Client Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Total Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open Tickets</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Resolved Tickets</h3><p><?= $resolved ?></p></div>
</div>

<?php if (hasPermission('roles.manage') || hasPermission('users.manage')): ?>
<div class="card mt-16">
    <div class="table-header">
        <h2>Roles Management</h2>
    </div>
    <?php if (hasPermission('users.manage')): ?><a class="btn" href="/atms/client/users.php">Manage Users</a><?php endif; ?>
    <?php if (hasPermission('roles.manage')): ?><a class="btn" href="/atms/client/roles.php">Manage Roles</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="card mt-16">
<div class="card sub-card">
    <div class="table-header">
        <h2>Recent Tickets</h2>
        <a class="btn" href="/atms/client/raise_ticket.php">Raise Ticket</a>
    </div>
    <table>
        <thead><tr><th>Ticket ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$recentTickets): ?>
            <tr><td colspan="6" class="muted">No tickets yet.</td></tr>
        <?php else: ?>
            <?php foreach ($recentTickets as $ticket): ?>
                <tr>
                    <td><?= e($ticket['ticket_id']) ?></td>
                    <td><?= e($ticket['subject']) ?></td>
                    <td><span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></td>
                    <td><span class="<?= priorityClass($ticket['priority']) ?>"><?= e(ucfirst($ticket['priority'])) ?></span></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($ticket['created_at']))) ?></td>
                    <td><a href="/atms/client/ticket_view.php?id=<?= (int) $ticket['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
