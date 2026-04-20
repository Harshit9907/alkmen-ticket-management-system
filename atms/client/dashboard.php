<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'manager', 'admin', 'client_admin']);

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = (string) $_SESSION['role'];
$scope = buildTicketScopeFilter($pdo, $sessionUserId, $sessionRole, 't.user_id');

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $scope['sql']);
$totalStmt->execute($scope['params']);
$total = (int) $totalStmt->fetchColumn();

$openStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE {$scope['sql']} AND t.status = 'open'");
$openStmt->execute($scope['params']);
$open = (int) $openStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE {$scope['sql']} AND t.status = 'resolved'");
$resolvedStmt->execute($scope['params']);
$resolved = (int) $resolvedStmt->fetchColumn();

$recentStmt = $pdo->prepare(
    'SELECT t.id, t.ticket_id, t.subject, t.status, t.priority, t.created_at, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id
     WHERE ' . $scope['sql'] . '
     ORDER BY t.created_at DESC
     LIMIT 5'
);
$recentStmt->execute($scope['params']);
$recentTickets = $recentStmt->fetchAll();

$pageTitle = 'Scoped Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Total Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open Tickets</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Resolved Tickets</h3><p><?= $resolved ?></p></div>
</div>

<div class="card mt-16">
    <div class="table-header">
        <h2>Recent Scoped Tickets</h2>
        <?php if ($sessionRole === 'client'): ?>
            <a class="btn" href="/atms/client/raise_ticket.php">Raise Ticket</a>
        <?php endif; ?>
    </div>
    <table data-sortable>
        <thead>
            <tr>
                <th data-sort="text">Ticket ID</th>
                <th data-sort="text">Client</th>
                <th data-sort="text">Subject</th>
                <th data-sort="text">Status</th>
                <th data-sort="text">Priority</th>
                <th data-sort="date">Created</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$recentTickets): ?>
            <tr><td colspan="7" class="muted">No tickets in your visibility scope.</td></tr>
        <?php else: ?>
            <?php foreach ($recentTickets as $ticket): ?>
                <tr>
                    <td><?= e($ticket['ticket_id']) ?></td>
                    <td><?= e($ticket['client_name']) ?></td>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
