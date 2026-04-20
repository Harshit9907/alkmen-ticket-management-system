<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client_admin', 'super_admin']);
requireRole(['admin', 'super_admin']);

$status = in_array($_GET['status'] ?? '', ['open', 'in_progress', 'resolved'], true) ? $_GET['status'] : '';
$priority = in_array($_GET['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_GET['priority'] : '';

$query = 'SELECT t.id, t.ticket_id, t.subject, t.status, t.priority, t.created_at, t.sla_deadline, t.is_overdue, u.name, a.name AS assignee
          FROM tickets t
          JOIN users u ON u.id = t.user_id
          LEFT JOIN users a ON a.id = t.assigned_to
          WHERE 1=1';
$params = [];

if ($_SESSION['role'] !== 'super_admin') {
    $query .= ' AND u.company_id = :company_id';
    $params['company_id'] = currentCompanyId();
}
if ($status !== '') {
    $query .= ' AND t.status = :status';
    $params['status'] = $status;
}
if ($priority !== '') {
    $query .= ' AND t.priority = :priority';
    $params['priority'] = $priority;
}
$query .= ' ORDER BY t.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'All Tickets';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <div class="table-header">
        <h2>Tickets</h2>
        <h2>Tickets Management</h2>
        <form method="GET" class="filters">
            <select name="status">
                <option value="">All Status</option>
                <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
            <select name="priority">
                <option value="">All Priority</option>
                <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
            </select>
            <button type="submit" class="btn">Apply</button>
        </form>
    </div>
    <table>
        <thead><tr><th>Ticket ID</th><th>Client</th><th>Subject</th><th>Status</th><th>Priority</th><th>Assigned To</th><th>Date</th><th>Action</th></tr></thead>
    <table data-sortable>
        <thead><tr><th data-sort="text">Ticket ID</th><th data-sort="text">Client</th><th data-sort="text">Status</th><th data-sort="text">Priority</th><th data-sort="text">Assigned To</th><th data-sort="date">Deadline</th><th data-sort="text">Overdue</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$tickets): ?>
            <tr><td colspan="8" class="muted">No matching tickets.</td></tr>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= e($ticket['ticket_id']) ?></td>
                    <td><?= e($ticket['name']) ?></td>
                    <td><span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></td>
                    <td><span class="<?= priorityClass($ticket['priority']) ?>"><?= e(ucfirst($ticket['priority'])) ?></span></td>
                    <td><?= e($ticket['assignee'] ?? 'Unassigned') ?></td>
                    <td><?= $ticket['sla_deadline'] ? e(date('M d, Y h:i A', strtotime($ticket['sla_deadline']))) : 'Not set' ?></td>
                    <td><span class="overdue <?= $ticket['is_overdue'] ? 'overdue-yes' : 'overdue-no' ?>"><?= $ticket['is_overdue'] ? 'Yes' : 'No' ?></span></td>
                    <td><a href="/atms/admin/ticket_view.php?id=<?= (int) $ticket['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
