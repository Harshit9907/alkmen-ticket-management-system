<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'client_plus', 'client_support']);

$ticketPk = (int) ($_GET['id'] ?? 0);
$ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id AND user_id = :user_id LIMIT 1');
$ticketStmt->execute(['id' => $ticketPk, 'user_id' => (int) $_SESSION['user_id']]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    redirect('/atms/client/my_tickets.php');
}

$error = '';
$canReply = canClientRaiseOrReply((string) ($_SESSION['role'] ?? 'client'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canReply) {
        $error = 'Read-only thread mode is enabled for your role.';
    }

    $message = trim($_POST['message'] ?? '');
    $file = null;

    if (!empty($_FILES['file']['name'])) {
        $file = uploadFile($_FILES['file']);
        if ($file === null) {
            $error = 'Invalid attachment file.';
        }
    }

    if ($message === '' && $file === null) {
        $error = 'Please enter a message or upload a file.';
    }

    if ($error === '') {
        $insertMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
        $insertMsg->execute([
            'ticket_id' => $ticketPk,
            'sender_id' => (int) $_SESSION['user_id'],
            'message' => $message !== '' ? $message : 'Shared an attachment.',
            'file' => $file,
        ]);
        redirect('/atms/client/ticket_view.php?id=' . $ticketPk);
    }
}

$msgStmt = $pdo->prepare('SELECT m.*, u.role, u.name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = :ticket_id ORDER BY m.created_at ASC');
$msgStmt->execute(['ticket_id' => $ticketPk]);
$messages = $msgStmt->fetchAll();

$eventStmt = $pdo->prepare('SELECT te.*, u.name AS actor_name FROM ticket_events te JOIN users u ON u.id = te.actor_id WHERE te.ticket_id = :ticket_id ORDER BY te.created_at DESC');
$eventStmt->execute(['ticket_id' => $ticketPk]);
$events = $eventStmt->fetchAll();

$pageTitle = 'Ticket ' . $ticket['ticket_id'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <h2><?= e($ticket['subject']) ?> (<?= e($ticket['ticket_id']) ?>)</h2>
    <p class="muted">Category: <?= e($ticket['category']) ?> | Status: <span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></p>
    <p class="muted">SLA Deadline: <?= $ticket['sla_deadline'] ? e(date('M d, Y h:i A', strtotime($ticket['sla_deadline']))) : 'Not set' ?> | Overdue: <?= $ticket['is_overdue'] ? 'Yes' : 'No' ?></p>
    <p><?= e($ticket['description']) ?></p>

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

    <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
    <?php if (!$canReply): ?><p class="muted">Thread is read-only for your role.</p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="chat-form">
        <input type="text" name="message" placeholder="Write your reply..." <?= $canReply ? '' : 'disabled' ?>>
        <input type="file" name="file" <?= $canReply ? '' : 'disabled' ?>>
        <button class="btn" type="submit" <?= $canReply ? '' : 'disabled' ?>>Send</button>
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
