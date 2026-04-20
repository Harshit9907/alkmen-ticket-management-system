<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';
requireRole(['client_admin']);

$role = (string) $_SESSION['role'];
$scope = scopeConditionForRole($role);
$params = scopeParamsForRole($role, $_SESSION);

$total = runScopedCount($pdo, $role, $_SESSION);
$open = runScopedCount($pdo, $role, $_SESSION, "t.status IN ('open', 'in_progress')");
$resolved = runScopedCount($pdo, $role, $_SESSION, "t.status = 'resolved'");

$userStmt = $pdo->prepare(
    "SELECT owner.id, owner.name, COUNT(*) AS total_tickets,
            SUM(CASE WHEN t.status IN ('open', 'in_progress') THEN 1 ELSE 0 END) AS open_tickets,
            SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     LEFT JOIN users assignee ON assignee.id = t.assigned_to
     WHERE {$scope}
     GROUP BY owner.id, owner.name
     ORDER BY total_tickets DESC, owner.name ASC"
);
$userStmt->execute($params);
$userActivity = $userStmt->fetchAll();

$statusStmt = $pdo->prepare(
    "SELECT t.status, COUNT(*) AS total
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     LEFT JOIN users assignee ON assignee.id = t.assigned_to
     WHERE {$scope}
     GROUP BY t.status"
);
$statusStmt->execute($params);
$statusDistribution = $statusStmt->fetchAll();

$pageTitle = 'Client Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-3">
    <div class="card stat-card"><h3>Company Total</h3><p><?= $total ?></p></div>
    <div class="card stat-card"><h3>Open/In Progress</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Resolved</h3><p><?= $resolved ?></p></div>
</div>

<div class="card mt-16">
    <h2>Per-User Ticket Activity</h2>
    <table data-sortable>
        <thead><tr><th>User</th><th>Total</th><th>Open</th><th>Resolved</th></tr></thead>
        <tbody>
            <?php if (!$userActivity): ?>
                <tr><td colspan="4" class="muted">No user activity in your company scope.</td></tr>
            <?php else: ?>
                <?php foreach ($userActivity as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td><?= (int) $row['total_tickets'] ?></td>
                        <td><?= (int) $row['open_tickets'] ?></td>
                        <td><?= (int) $row['resolved_tickets'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card mt-16">
    <h2>Status Distribution</h2>
    <table>
        <thead><tr><th>Status</th><th>Total</th></tr></thead>
        <tbody>
            <?php foreach ($statusDistribution as $row): ?>
                <tr>
                    <td><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
                    <td><?= (int) $row['total'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
