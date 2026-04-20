<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
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

$pageTitle = 'Admin Dashboard';
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
        <h2>Recent Activity</h2>
        <a class="btn" href="/atms/admin/tickets.php">Manage Tickets</a>
    </div>
    <table>
        <thead><tr><th>Ticket</th><th>Client</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (!$activity): ?>
            <tr><td colspan="5" class="muted">No activity yet.</td></tr>
        <?php else: ?>
            <?php foreach ($activity as $row): ?>
                <tr>
                    <td><?= e($row['ticket_id']) ?></td>
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
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
