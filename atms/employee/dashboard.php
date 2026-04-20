<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';
requireRole(['employee', 'client']);

$role = (string) $_SESSION['role'];

$open = runScopedCount($pdo, $role, $_SESSION, "t.status IN ('open', 'in_progress')");
$resolved = runScopedCount($pdo, $role, $_SESSION, "t.status = 'resolved'");

$scope = scopeConditionForRole($role);
$params = scopeParamsForRole($role, $_SESSION);

$recentStmt = $pdo->prepare(
    "SELECT t.ticket_id, t.subject, t.status, t.priority, t.created_at
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     LEFT JOIN users assignee ON assignee.id = t.assigned_to
     WHERE {$scope}
     ORDER BY t.created_at DESC
     LIMIT 8"
);
$recentStmt->execute($params);
$recentActivity = $recentStmt->fetchAll();

$pageTitle = 'Employee Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-2">
    <div class="card stat-card"><h3>Own Open/In Progress</h3><p><?= $open ?></p></div>
    <div class="card stat-card"><h3>Own Resolved</h3><p><?= $resolved ?></p></div>
</div>

<div class="card mt-16">
    <h2>Recent Own Activity</h2>
    <table data-sortable>
        <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Priority</th><th>Created</th></tr></thead>
        <tbody>
            <?php if (!$recentActivity): ?>
                <tr><td colspan="5" class="muted">No activity yet in your scope.</td></tr>
            <?php else: ?>
                <?php foreach ($recentActivity as $row): ?>
                    <tr>
                        <td><?= e($row['ticket_id']) ?></td>
                        <td><?= e($row['subject']) ?></td>
                        <td><span class="<?= badgeClass($row['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></span></td>
                        <td><span class="<?= priorityClass($row['priority']) ?>"><?= e(ucfirst($row['priority'])) ?></span></td>
                        <td><?= e(date('M d, Y h:i A', strtotime($row['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
