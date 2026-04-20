<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['admin', 'manager', 'client_admin']);
requireRole(['client_admin', 'super_admin']);

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = (string) $_SESSION['role'];
$ticketPk = (int) ($_GET['id'] ?? 0);
$companyId = (int) $_SESSION['company_id'];
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
requireRole(['admin', 'super_admin']);

if ($ticketPk <= 0 || !canAccessTicket($pdo, $ticketPk, $sessionUserId, $sessionRole)) {
    redirect('/atms/client/my_tickets.php');
}

$ticketPk = (int) ($_GET['id'] ?? 0);
$ticketStmt = $pdo->prepare(
    'SELECT t.*, u.name AS client_name, a.name AS assignee_name
     FROM tickets t
     JOIN users u ON u.id = t.user_id AND u.company_id = t.company_id
     LEFT JOIN users a ON a.id = t.assigned_to AND a.company_id = t.company_id
     WHERE t.id = :id AND t.company_id = :company_id
     LIMIT 1'
);
$ticketStmt->execute([
    'id' => $ticketPk,
    'company_id' => $companyId,
]);
$ticketStmt->execute(['id' => $ticketPk]);
$ticket = $ticketStmt->fetch();
if (!$ticket) {
    redirect('/atms/client/my_tickets.php');
}

$adminsStmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND company_id = :company_id ORDER BY name ASC");
$adminsStmt->execute(['company_id' => $companyId]);
$admins = $adminsStmt->fetchAll();
$assignees = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin', 'client_admin', 'manager') ORDER BY name ASC")->fetchAll();
$adminsStmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'client_admin' AND company_id = :company_id ORDER BY name ASC");
$adminsStmt->execute(['company_id' => (int) $ticket['company_id']]);
$admins = $adminsStmt->fetchAll();
$admins = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin', 'super_admin') ORDER BY name ASC")->fetchAll();
$error = '';
$canAct = canManageTicketActions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved'], true) ? $_POST['status'] : $ticket['status'];
    $assignTo = (int) ($_POST['assigned_to'] ?? 0);
    $validAssigneeIds = array_map(static fn(array $u): int => (int) $u['id'], $assignees);

    if (!in_array($assignTo, $validAssigneeIds, true)) {
        $assignTo = $sessionUserId;
    }

    $update = $pdo->prepare('UPDATE tickets SET status = :status, assigned_to = :assigned_to WHERE id = :id AND company_id = :company_id');
    $update->execute([
        'status' => $status,
        'assigned_to' => $assignTo,
        'id' => $ticketPk,
        'company_id' => $companyId,
    ]);

    $message = trim($_POST['message'] ?? '');
    $file = null;

    if (!empty($_FILES['file']['name'])) {
        $file = uploadFile($_FILES['file']);
        if ($file === null) {
            $error = 'Attachment upload failed.';
    if (!$canAct) {
        $error = 'Only super_admin can change status, assignment, SLA, or send official support replies.';
    } else {
        $status = in_array($_POST['status'] ?? '', ['open', 'in_progress', 'resolved'], true) ? $_POST['status'] : $ticket['status'];
        $assignTo = (int) ($_POST['assigned_to'] ?? 0);
        $deadlineInput = trim($_POST['sla_deadline'] ?? '');
        $deadline = $deadlineInput !== '' ? date('Y-m-d H:i:s', strtotime($deadlineInput)) : null;

        $isValidAdmin = false;
        foreach ($admins as $admin) {
            if ((int) $admin['id'] === $assignTo) {
                $isValidAdmin = true;
                break;
            }
        }
        if (!$isValidAdmin) {
            $assignTo = (int) ($ticket['assigned_to'] ?? 0);
        }
    }

    if ($error === '' && ($message !== '' || $file !== null)) {
        $insertMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
        $insertMsg->execute([
            'ticket_id' => $ticketPk,
            'sender_id' => $sessionUserId,
            'message' => $message !== '' ? $message : 'Shared an attachment.',
            'file' => $file,
        ]);
    }

    if ($error === '') {
        redirect('/atms/admin/ticket_view.php?id=' . $ticketPk);

    if ($error === '') {
        redirect('/atms/admin/ticket_view.php?id=' . $ticketPk);
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
        $isOverdue = $deadline !== null && strtotime($deadline) < time() ? 1 : 0;

        $update = $pdo->prepare('UPDATE tickets SET status = :status, assigned_to = :assigned_to, sla_deadline = :sla_deadline, is_overdue = :is_overdue WHERE id = :id');
        $update->execute([
            'status' => $status,
            'assigned_to' => $assignTo > 0 ? $assignTo : null,
            'sla_deadline' => $deadline,
            'is_overdue' => $isOverdue,
            'id' => $ticketPk,
        ]);

        if ($status !== $ticket['status']) {
            logTicketEvent($pdo, $ticketPk, 'status_change', (string) $ticket['status'], $status, (int) $_SESSION['user_id']);
        }
        if ((int) ($ticket['assigned_to'] ?? 0) !== $assignTo) {
            logTicketEvent($pdo, $ticketPk, 'assignment_change', (string) ($ticket['assigned_to'] ?? 'unassigned'), (string) ($assignTo ?: 'unassigned'), (int) $_SESSION['user_id']);
        }

        $message = trim($_POST['message'] ?? '');
        $file = null;
        if (!empty($_FILES['file']['name'])) {
            $file = uploadFile($_FILES['file']);
            if ($file === null) {
                $error = 'Attachment upload failed. Please use valid file type.';
            }
        }

        if ($error === '' && ($message !== '' || $file !== null)) {
            $insertMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
            $insertMsg->execute([
                'ticket_id' => $ticketPk,
                'sender_id' => (int) $_SESSION['user_id'],
                'message' => $message !== '' ? $message : 'Shared an attachment.',
                'file' => $file,
            ]);
        }

        if ($error === '') {
            redirect('/atms/admin/ticket_view.php?id=' . $ticketPk);
        }
    }
}

$msgStmt = $pdo->prepare(
    'SELECT m.*, u.role, u.name
     FROM messages m
     JOIN tickets t ON t.id = m.ticket_id
     JOIN users u ON u.id = m.sender_id AND u.company_id = t.company_id
     WHERE m.ticket_id = :ticket_id
       AND t.company_id = :company_id
     ORDER BY m.created_at ASC'
);
$msgStmt->execute([
    'ticket_id' => $ticketPk,
    'company_id' => $companyId,
]);
$messages = $msgStmt->fetchAll();

$eventStmt = $pdo->prepare('SELECT te.*, u.name AS actor_name FROM ticket_events te JOIN users u ON u.id = te.actor_id WHERE te.ticket_id = :ticket_id ORDER BY te.created_at DESC');
$eventStmt->execute(['ticket_id' => $ticketPk]);
$events = $eventStmt->fetchAll();

$pageTitle = 'Manage ' . $ticket['ticket_id'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <h2><?= e($ticket['subject']) ?> (<?= e($ticket['ticket_id']) ?>)</h2>
    <p class="muted">Client: <?= e($ticket['client_name']) ?> | Current: <span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></p>

    <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$canAct): ?><p class="muted">Read-only admin mode: only super_admin can perform ticket actions.</p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="management-form">
        <div>
            <label>Status</label>
            <select name="status" <?= $canAct ? '' : 'disabled' ?>>
                <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            </select>
        </div>
        <div>
            <label>Assign Ticket</label>
            <select name="assigned_to">
                <?php foreach ($assignees as $assignee): ?>
                    <option value="<?= (int) $assignee['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $assignee['id'] ? 'selected' : '' ?>>
                        <?= e($assignee['name']) ?>
                    </option>
                <option value="0">Unassigned</option>
            <select name="assigned_to" <?= $canAct ? '' : 'disabled' ?>>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?= (int) $admin['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $admin['id'] ? 'selected' : '' ?>><?= e($admin['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

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
        <input type="text" name="message" placeholder="Reply to ticket...">
        <input type="file" name="file">

        <button type="submit" class="btn">Save Changes & Send Reply</button>
    </form>
        <div>
            <label>Reply (optional)</label>
            <input type="text" name="message" placeholder="Reply to ticket...">
        </div>
        <div>
            <label>Attachment (optional)</label>
            <input type="file" name="file">
        </div>
        <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
        <button type="submit" class="btn">Save Changes</button>
        <div>
            <label>SLA Deadline</label>
            <input type="datetime-local" name="sla_deadline" value="<?= $ticket['sla_deadline'] ? e(date('Y-m-d\TH:i', strtotime($ticket['sla_deadline']))) : '' ?>" <?= $canAct ? '' : 'disabled' ?>>
        </div>
        <button type="submit" class="btn" <?= $canAct ? '' : 'disabled' ?>>Save Changes</button>
    </form>

    <div class="chat-box">
        <?php foreach ($messages as $message): ?>
            <div class="chat-message <?= str_starts_with($message['role'], 'client') ? 'left' : 'right' ?>">
                <strong><?= e($message['name']) ?></strong>
                <p><?= nl2br(e($message['message'])) ?></p>
                <?php if (!empty($message['file'])): ?>
                    <a href="/atms/assets/uploads/<?= e($message['file']) ?>" target="_blank">Open Attachment</a>
                <?php endif; ?>
                <small class="muted"><?= e(date('M d, Y h:i A', strtotime($message['created_at']))) ?></small>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" class="chat-form">
        <input type="hidden" name="status" value="<?= e($ticket['status']) ?>">
        <input type="hidden" name="assigned_to" value="<?= (int) ($ticket['assigned_to'] ?? 0) ?>">
        <input type="text" name="message" placeholder="Reply to ticket...">
        <input type="file" name="file">
        <button type="submit" class="btn">Send Reply</button>
    </form>
</div>
</div>
        <input type="hidden" name="sla_deadline" value="<?= $ticket['sla_deadline'] ? e(date('Y-m-d\TH:i', strtotime($ticket['sla_deadline']))) : '' ?>">
        <input type="text" name="message" placeholder="Official support reply..." <?= $canAct ? '' : 'disabled' ?>>
        <input type="file" name="file" <?= $canAct ? '' : 'disabled' ?>>
        <button type="submit" class="btn" <?= $canAct ? '' : 'disabled' ?>>Send Reply</button>
    </form>

    <div class="timeline">
        <h3>Ticket Timeline</h3>
        <?php if (!$events): ?>
            <p class="muted">No events logged yet.</p>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <div class="timeline-item">
                    <h4><?= e($event['event_type'] === 'status_change' ? 'Status Changed' : 'Assignment Changed') ?></h4>
                    <p><?= e((string) $event['old_value']) ?> → <?= e((string) $event['new_value']) ?></p>
                    <small class="muted">By <?= e($event['actor_name']) ?> on <?= e(date('M d, Y h:i A', strtotime($event['created_at']))) ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
