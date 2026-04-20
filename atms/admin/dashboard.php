<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['admin', 'super_admin']);

$total = (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open', 'in_progress')")->fetchColumn();
$resolved = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'")->fetchColumn();
$overdue = (int) $pdo->query('SELECT COUNT(*) FROM tickets WHERE is_overdue = 1')->fetchColumn();

$activity = $pdo->query(
    "SELECT t.ticket_id, t.subject, t.status, t.created_at, t.sla_deadline, t.is_overdue, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC
     LIMIT 8"
)->fetchAll();

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
        <h2>Recent Activity</h2>
        <a class="btn" href="/atms/admin/tickets.php">Manage Tickets</a>
    </div>
    <table data-sortable>
        <thead><tr><th data-sort="text">Ticket</th><th data-sort="text">Client</th><th data-sort="text">Status</th><th data-sort="date">SLA Deadline</th><th data-sort="text">Overdue</th></tr></thead>
        <tbody>
        <?php if (!$activity): ?>
            <tr><td colspan="5" class="muted">No activity yet.</td></tr>
        <?php else: ?>
            <?php foreach ($activity as $row): ?>
                <tr>
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
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
