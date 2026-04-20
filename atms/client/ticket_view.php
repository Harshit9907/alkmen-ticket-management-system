<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'manager', 'admin', 'client_admin']);

$sessionUserId = (int) $_SESSION['user_id'];
$sessionRole = (string) $_SESSION['role'];
$ticketPk = (int) ($_GET['id'] ?? 0);

if ($ticketPk <= 0 || !canAccessTicket($pdo, $ticketPk, $sessionUserId, $sessionRole)) {
    redirect('/atms/client/my_tickets.php');
}

$ticketStmt = $pdo->prepare('SELECT t.*, u.name AS client_name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = :id LIMIT 1');
$ticketStmt->execute(['id' => $ticketPk]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    redirect('/atms/client/my_tickets.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            'sender_id' => $sessionUserId,
            'message' => $message !== '' ? $message : 'Shared an attachment.',
            'file' => $file,
        ]);
        redirect('/atms/client/ticket_view.php?id=' . $ticketPk);
    }
}

$msgStmt = $pdo->prepare('SELECT m.*, u.role, u.name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.ticket_id = :ticket_id ORDER BY m.created_at ASC');
$msgStmt->execute(['ticket_id' => $ticketPk]);
$messages = $msgStmt->fetchAll();

$pageTitle = 'Ticket ' . $ticket['ticket_id'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <h2><?= e($ticket['subject']) ?> (<?= e($ticket['ticket_id']) ?>)</h2>
    <p class="muted">Client: <?= e($ticket['client_name']) ?> | Category: <?= e($ticket['category']) ?> | Status: <span class="<?= badgeClass($ticket['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ticket['status']))) ?></span></p>
    <p><?= e($ticket['description']) ?></p>
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
        <input type="text" name="message" placeholder="Write your reply...">
        <input type="file" name="file">
        <button class="btn" type="submit">Send</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
