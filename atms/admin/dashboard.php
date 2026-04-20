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
require_once __DIR__ . '/../includes/dashboard_scope.php';

redirect(currentDashboardRoute((string) $_SESSION['role']));
requireRole(['client_admin', 'super_admin']);

$companyFilter = '';
$params = [];
if ($_SESSION['role'] !== 'super_admin') {
    $companyFilter = ' WHERE u.company_id = :company_id ';
    $params['company_id'] = currentCompanyId();
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id {$companyFilter}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id {$companyFilter} " . ($companyFilter ? ' AND ' : ' WHERE ') . "t.status IN ('open', 'in_progress')");
$pendingStmt->execute($params);
$pending = (int) $pendingStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets t JOIN users u ON u.id = t.user_id {$companyFilter} " . ($companyFilter ? ' AND ' : ' WHERE ') . "t.status = 'resolved'");
$resolvedStmt->execute($params);
$resolved = (int) $resolvedStmt->fetchColumn();

$activitySql = "SELECT t.ticket_id, t.subject, t.status, t.created_at, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id";
if ($companyFilter) {
    $activitySql .= ' WHERE u.company_id = :company_id';
}
$activitySql .= ' ORDER BY t.created_at DESC LIMIT 8';
$activityStmt = $pdo->prepare($activitySql);
$activityStmt->execute($params);
$activity = $activityStmt->fetchAll();

requireRole(['admin', 'super_admin']);

$companyId = (int) $_SESSION['company_id'];

$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE company_id = :company_id');
$totalStmt->execute(['company_id' => $companyId]);
$total = (int) $totalStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = :company_id AND status IN ('open', 'in_progress')");
$pendingStmt->execute(['company_id' => $companyId]);
$pending = (int) $pendingStmt->fetchColumn();

$resolvedStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE company_id = :company_id AND status = 'resolved'");
$resolvedStmt->execute(['company_id' => $companyId]);
$resolved = (int) $resolvedStmt->fetchColumn();

$activityStmt = $pdo->prepare(
    "SELECT t.ticket_id, t.subject, t.status, t.created_at, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id AND u.company_id = t.company_id
     WHERE t.company_id = :company_id
     ORDER BY t.created_at DESC
     LIMIT 8"
);
$activityStmt->execute(['company_id' => $companyId]);
$activity = $activityStmt->fetchAll();
$total = (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress')")->fetchColumn();
$resolved = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'")->fetchColumn();
$overdue = (int) $pdo->query('SELECT COUNT(*) FROM tickets WHERE is_overdue = 1')->fetchColumn();

$activity = $pdo->query(
    "SELECT t.ticket_id, t.subject, t.status, t.created_at, t.sla_deadline, t.is_overdue, u.name AS client_name
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
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Total Tickets</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Pending Tickets</h3><p><?= $pending ?></p></div>
    <div class="card stat-card"><h3>Resolved Tickets</h3><p><?= $resolved ?></p></div>
</div>

<div class="card sub-card stat-card">
    <h3>Overdue Tickets</h3>
    <p><?= $overdue ?></p>
</div>

<div class="card sub-card">
    <div class="table-header">
        <h2>Recent Activity (Scoped)</h2>
        <a class="btn" href="/atms/client/my_tickets.php">Open Ticket List</a>
    </div>
    <table>
        <thead><tr><th>Ticket</th><th>Client</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
    <table data-sortable>
        <thead><tr><th>Ticket</th><th>Client</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <thead><tr><th data-sort="text">Ticket</th><th data-sort="text">Client</th><th data-sort="text">Status</th><th data-sort="date">SLA Deadline</th><th data-sort="text">Overdue</th></tr></thead>
        <tbody>
        <?php if (!$activity): ?>
            <tr><td colspan="5" class="muted">No scoped activity yet.</td></tr>
        <?php else: ?>
            <?php foreach ($activity as $row): ?>
                <tr>
                    <td><a href="/atms/client/ticket_view.php?id=<?= (int) $row['id'] ?>"><?= e($row['ticket_id']) ?></a></td>
                    <td><?= e($row['ticket_id']) ?> - <?= e($row['subject']) ?></td>
                    <td><?= e($row['client_name']) ?></td>
                    <td><span class="<?= badgeClass($row['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></span></td>
                    <td><?= $row['sla_deadline'] ? e(date('M d, Y h:i A', strtotime($row['sla_deadline']))) : 'Not set' ?></td>
                    <td><span class="overdue <?= $row['is_overdue'] ? 'overdue-yes' : 'overdue-no' ?>"><?= $row['is_overdue'] ? 'Yes' : 'No' ?></span></td>
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
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
