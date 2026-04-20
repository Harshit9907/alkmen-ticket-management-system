<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'client_plus', 'client_support']);

$allowedSort = ['created_at', 'priority', 'status'];
$sort = in_array($_GET['sort'] ?? 'created_at', $allowedSort, true) ? $_GET['sort'] : 'created_at';

$stmt = $pdo->prepare("SELECT id, ticket_id, subject, status, priority, created_at FROM tickets WHERE user_id = :user_id ORDER BY {$sort} DESC");
$stmt->execute(['user_id' => currentUserId()]);
$tickets = $stmt->fetchAll();

$pageTitle = 'My Tickets';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <div class="table-header">
        <h2>My Tickets</h2>
        <form method="GET">
            <select name="sort" onchange="this.form.submit()">
                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
            </select>
        </form>
    </div>
    <table>
        <thead><tr><th>Ticket ID</th><th>Subject</th><th>Status</th><th>Priority</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
            <?php if (!$tickets): ?>
                <tr><td colspan="6" class="muted">No tickets found.</td></tr>
            <?php else: ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?= e($ticket['ticket_id']) ?></td>
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
