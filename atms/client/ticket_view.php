<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);

$ticketPk = (int) ($_GET['id'] ?? 0);
$ticketStmt = $pdo->prepare('SELECT * FROM tickets WHERE id = :id AND user_id = :user_id LIMIT 1');
$ticketStmt->execute(['id' => $ticketPk, 'user_id' => (int) $_SESSION['user_id']]);
$ticket = $ticketStmt->fetch();

if (!$ticket) {
    redirect('/atms/client/my_tickets.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        $insertMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message) VALUES (:ticket_id, :sender_id, :message)');
        $insertMsg->execute([
            'ticket_id' => $ticketPk,
            'sender_id' => (int) $_SESSION['user_id'],
            'message' => $message,
        ]);
    }
    redirect('/atms/client/ticket_view.php?id=' . $ticketPk);
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
    <p><?= e($ticket['description']) ?></p>
    <div class="chat-box">
        <?php foreach ($messages as $message): ?>
            <div class="chat-message <?= $message['role'] === 'client' ? 'left' : 'right' ?>">
                <strong><?= e($message['name']) ?></strong>
                <p><?= nl2br(e($message['message'])) ?></p>
                <?php if (!empty($message['file'])): ?>
                    <a href="/atms/assets/uploads/<?= e($message['file']) ?>" target="_blank">Attachment</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="POST" class="chat-form">
        <input type="text" name="message" placeholder="Write your reply..." required>
        <button class="btn" type="submit">Send</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
