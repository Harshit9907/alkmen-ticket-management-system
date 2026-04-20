<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';
requireRole(['manager']);

$role = (string) $_SESSION['role'];
$scope = scopeConditionForRole($role);
$params = scopeParamsForRole($role, $_SESSION);

$teamOpen = runScopedCount($pdo, $role, $_SESSION, "t.status IN ('open', 'in_progress')");
$teamResolved = runScopedCount($pdo, $role, $_SESSION, "t.status = 'resolved'");

$workloadStmt = $pdo->prepare(
    "SELECT COALESCE(assignee.name, 'Unassigned') AS assignee_name,
            COUNT(*) AS total,
            SUM(CASE WHEN t.status IN ('open', 'in_progress') THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) AS resolved
     FROM tickets t
     JOIN users owner ON owner.id = t.user_id
     LEFT JOIN users assignee ON assignee.id = t.assigned_to
     WHERE {$scope}
     GROUP BY assignee_name
     ORDER BY active DESC, total DESC"
);
$workloadStmt->execute($params);
$teamWorkload = $workloadStmt->fetchAll();

$pageTitle = 'Manager Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="grid cards-2">
    <div class="card stat-card"><h3>Team Open/In Progress</h3><p><?= $teamOpen ?></p></div>
    <div class="card stat-card"><h3>Team Resolved</h3><p><?= $teamResolved ?></p></div>
</div>

<div class="card mt-16">
    <h2>Team Workload</h2>
    <table data-sortable>
        <thead><tr><th>Assignee</th><th>Total</th><th>Active</th><th>Resolved</th></tr></thead>
        <tbody>
            <?php if (!$teamWorkload): ?>
                <tr><td colspan="4" class="muted">No workload found in your team scope.</td></tr>
            <?php else: ?>
                <?php foreach ($teamWorkload as $row): ?>
                    <tr>
                        <td><?= e($row['assignee_name']) ?></td>
                        <td><?= (int) $row['total'] ?></td>
                        <td><?= (int) $row['active'] ?></td>
                        <td><?= (int) $row['resolved'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
