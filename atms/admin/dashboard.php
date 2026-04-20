<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['admin', 'manager', 'client_admin']);

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = (string) $_SESSION['role'];
$scope = buildTicketScopeFilter($pdo, $sessionUserId, $sessionRole, 't.user_id');

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets t WHERE ' . $scope['sql']);
$totalStmt->execute($scope['params']);
$total = (int) $totalStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE {$scope['sql']} AND t.status IN ('open', 'in_progress')");
$pendingStmt->execute($scope['params']);
$pending = (int) $pendingStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE {$scope['sql']} AND t.status = 'resolved'");
$resolvedStmt->execute($scope['params']);
$resolved = (int) $resolvedStmt->fetchColumn();

$activityStmt = $pdo->prepare(
    'SELECT t.id, t.ticket_id, t.subject, t.status, t.created_at, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id
     WHERE ' . $scope['sql'] . '
     ORDER BY t.created_at DESC
     LIMIT 8'
);
$activityStmt->execute($scope['params']);
$activity = $activityStmt->fetchAll();

$reportStmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email,
            COUNT(DISTINCT t.id) AS tickets_created,
            SUM(CASE WHEN t.status = "resolved" THEN 1 ELSE 0 END) AS tickets_resolved,
            COUNT(m.id) AS total_messages
     FROM users u
     LEFT JOIN tickets t ON t.user_id = u.id
     LEFT JOIN messages m ON m.ticket_id = t.id AND m.sender_id = u.id
     WHERE u.role = "client" AND ' . str_replace('t.user_id', 'u.id', $scope['sql']) . '
     GROUP BY u.id, u.name, u.email
     ORDER BY tickets_created DESC, total_messages DESC, u.name ASC'
);
$reportStmt->execute($scope['params']);
$contributions = $reportStmt->fetchAll();

$pageTitle = 'Team Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Total Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Pending Tickets</h3><p><?= $pending ?></p></div>
    <div class="card stat-card"><h3>Resolved Tickets</h3><p><?= $resolved ?></p></div>
</div>

<div class="card mt-16">
    <div class="table-header">
        <h2>Recent Activity (Scoped)</h2>
        <a class="btn" href="/atms/client/my_tickets.php">Open Ticket List</a>
    </div>
    <table data-sortable>
        <thead><tr><th>Ticket</th><th>Client</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (!$activity): ?>
            <tr><td colspan="5" class="muted">No scoped activity yet.</td></tr>
        <?php else: ?>
            <?php foreach ($activity as $row): ?>
                <tr>
                    <td><a href="/atms/client/ticket_view.php?id=<?= (int) $row['id'] ?>"><?= e($row['ticket_id']) ?></a></td>
                    <td><?= e($row['client_name']) ?></td>
                    <td><?= e($row['subject']) ?></td>
                    <td><span class="<?= badgeClass($row['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></span></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($row['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card mt-16" id="reports">
    <div class="table-header">
        <h2>Reports: Per-user Ticket Contribution</h2>
    </div>
    <table data-sortable>
        <thead><tr><th>Client</th><th>Email</th><th>Tickets Created</th><th>Resolved Tickets</th><th>Client Messages</th></tr></thead>
        <tbody>
        <?php if (!$contributions): ?>
            <tr><td colspan="5" class="muted">No users found for this scope.</td></tr>
        <?php else: ?>
            <?php foreach ($contributions as $user): ?>
                <tr>
                    <td><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= (int) $user['tickets_created'] ?></td>
                    <td><?= (int) $user['tickets_resolved'] ?></td>
                    <td><?= (int) $user['total_messages'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
