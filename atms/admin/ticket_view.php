<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client_admin', 'super_admin']);

$ticketPk = (int) ($_GET['id'] ?? 0);
$params = ['id' => $ticketPk];

$sql = 'SELECT t.*, u.name AS client_name, a.name AS assignee_name, u.company_id
        FROM tickets t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN users a ON a.id = t.assigned_to
        WHERE t.id = :id';
if ($_SESSION['role'] !== 'super_admin') {
    $sql .= ' AND u.company_id = :company_id';
    $params['company_id'] = currentCompanyId();
}
$sql .= ' LIMIT 1';

$ticketStmt = $pdo->prepare($sql);
$ticketStmt->execute($params);
$ticket = $ticketStmt->fetch();
if (!$ticket) {
    redirect('/atms/admin/tickets.php');
}

$adminsStmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'client_admin' AND company_id = :company_id ORDER BY name ASC");
$adminsStmt->execute(['company_id' => (int) $ticket['company_id']]);
$admins = $adminsStmt->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved'], true) ? $_POST['status'] : $ticket['status'];
    $assignTo = (int) ($_POST['assigned_to'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $file = null;

    if (!empty($_FILES['file']['name'])) {
        $file = uploadFile($_FILES['file']);
        if ($file === null) {
            $error = 'Attachment upload failed.';
        }
    }

    if ($error === '') {
        $update = $pdo->prepare('UPDATE tickets SET status = :status, assigned_to = :assigned_to WHERE id = :id');
        $update->execute(['status' => $status, 'assigned_to' => $assignTo ?: null, 'id' => $ticketPk]);

        if ($message !== '' || $file !== null) {
            $insertMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
            $insertMsg->execute([
                'ticket_id' => $ticketPk,
                'sender_id' => currentUserId(),
                'message' => $message !== '' ? $message : 'Shared an attachment.',
                'file' => $file,
            ]);
        }

        redirect('/atms/admin/ticket_view.php?id=' . $ticketPk);
    }
}

$msgStmt = $pdo->prepare('SELECT m.*, u.role, u.name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = :ticket_id ORDER BY m.created_at ASC');
$msgStmt->execute(['ticket_id' => $ticketPk]);
$messages = $msgStmt->fetchAll();

$pageTitle = 'Manage ' . $ticket['ticket_id'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <h2><?= e($ticket['subject']) ?> (<?= e($ticket['ticket_id']) ?>)</h2>
    <p class="muted">Client: <?= e($ticket['client_name']) ?> | Current: <span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></p>

    <form method="POST" enctype="multipart/form-data" class="management-form">
        <div>
            <label>Status</label>
            <select name="status">
                <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>
        <div>
            <label>Assign Ticket</label>
            <select name="assigned_to">
                <option value="0">Unassigned</option>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?= (int) $admin['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $admin['id'] ? 'selected' : '' ?>><?= e($admin['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Save Changes</button>
    </form>

    <div class="chat-box">
        <?php foreach ($messages as $message): ?>
            <div class="chat-message <?= $message['role'] === 'client' ? 'left' : 'right' ?>">
                <strong><?= e($message['name']) ?></strong>
                <p><?= nl2br(e($message['message'])) ?></p>
                <?php if (!empty($message['file'])): ?>
                    <a href="/atms/assets/uploads/<?= e($message['file']) ?>" target="_blank">Open Attachment</a>
                <?php endif; ?>
                <small class="muted"><?= e(date('M d, Y h:i A', strtotime($message['created_at']))) ?></small>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="chat-form">
        <input type="hidden" name="status" value="<?= e($ticket['status']) ?>">
        <input type="hidden" name="assigned_to" value="<?= (int) ($ticket['assigned_to'] ?? 0) ?>">
        <input type="text" name="message" placeholder="Reply to ticket...">
        <input type="file" name="file">
        <button type="submit" class="btn">Send Reply</button>
    </form>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
