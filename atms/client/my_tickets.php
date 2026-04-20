<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'manager', 'admin', 'client_admin']);
requireRole(['client', 'client_plus', 'client_support']);

$allowedSort = ['created_at', 'priority', 'status'];
$sort = in_array($_GET['sort'] ?? 'created_at', $allowedSort, true) ? $_GET['sort'] : 'created_at';

$stmt = $pdo->prepare("SELECT id, ticket_id, subject, status, priority, created_at FROM tickets WHERE user_id = :user_id AND company_id = :company_id ORDER BY {$sort} DESC");
$stmt->execute([
    'user_id' => (int) $_SESSION['user_id'],
    'company_id' => (int) $_SESSION['company_id'],
]);
$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = (string) $_SESSION['role'];
$scope = buildTicketScopeFilter($pdo, $sessionUserId, $sessionRole, 't.user_id');

$stmt = $pdo->prepare(
    "SELECT t.id, t.ticket_id, t.subject, t.status, t.priority, t.created_at, u.name AS client_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id
     WHERE {$scope['sql']}
     ORDER BY t.{$sort} DESC"
);
$stmt->execute($scope['params']);
$stmt = $pdo->prepare("SELECT id, ticket_id, subject, status, priority, created_at FROM tickets WHERE user_id = :user_id ORDER BY {$sort} DESC");
$stmt->execute(['user_id' => currentUserId()]);
$tickets = $stmt->fetchAll();

$pageTitle = 'Scoped Tickets';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <div class="table-header">
        <h2>Tickets in My Scope</h2>
        <form method="GET">
            <select name="sort" onchange="this.form.submit()">
                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
        </form>
    </div>
    <table data-sortable>
        <thead><tr><th data-sort="text">Ticket ID</th><th data-sort="text">Client</th><th data-sort="text">Subject</th><th data-sort="text">Status</th><th data-sort="text">Priority</th><th data-sort="date">Date</th><th>Action</th></tr></thead>
    <table>
        <thead><tr><th>Ticket ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
            <?php if (!$tickets): ?>
                <tr><td colspan="7" class="muted">No tickets found in your scope.</td></tr>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
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
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
