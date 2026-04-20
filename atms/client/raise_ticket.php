<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client', 'client_plus', 'client_support']);

$errors = [];
$role = (string) ($_SESSION['role'] ?? 'client');
$canRaise = canClientRaiseOrReply($role);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canRaise) {
        $errors[] = 'Read-only mode enabled for your role. Raising tickets requires explicit business-rule permission.';
    }

    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'low';

    if ($subject === '' || strlen($subject) < 3) {
        $errors[] = 'Subject must be at least 3 characters.';
    }
    if ($description === '' || strlen($description) < 10) {
        $errors[] = 'Description must be at least 10 characters.';
    }

    $uploadName = null;
    if (!empty($_FILES['file']['name'])) {
        $uploadName = uploadFile($_FILES['file']);
        if ($uploadName === null) {
            $errors[] = 'Invalid file or upload failed.';
        }
    }

    if (!$errors) {
        $ticketId = generateTicketId($pdo);
        $ticketStmt = $pdo->prepare('INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status) VALUES (:ticket_id, :user_id, :subject, :description, :category, :priority, :status)');
        $ticketStmt->execute([
            'ticket_id' => $ticketId,
            'user_id' => (int) $_SESSION['user_id'],
            'subject' => $subject,
            'description' => $description,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
        ]);

        $ticketPk = (int) $pdo->lastInsertId();
        $initialMsg = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
        $initialMsg->execute([
            'ticket_id' => $ticketPk,
            'sender_id' => (int) $_SESSION['user_id'],
            'message' => 'Ticket created: ' . $description,
            'file' => $uploadName,
        ]);

        redirect('/atms/client/ticket_view.php?id=' . $ticketPk);
    }
}

$pageTitle = 'Raise Ticket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card form-card">
    <h2>Raise a New Ticket</h2>
    <?php foreach ($errors as $error): ?><p class="alert-error"><?= e($error) ?></p><?php endforeach; ?>
    <?php if (!$canRaise): ?><p class="muted">Your role currently has read-only ticket access.</p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Subject</label>
        <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required <?= $canRaise ? '' : 'disabled' ?>>
        <label>Category</label>
        <select name="category" required <?= $canRaise ? '' : 'disabled' ?>>
            <option value="General">General</option>
            <option value="Technical">Technical</option>
            <option value="Billing">Billing</option>
            <option value="Account">Account</option>
        </select>
        <label>Priority</label>
        <select name="priority" required <?= $canRaise ? '' : 'disabled' ?>>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
        <label>Description</label>
        <textarea name="description" rows="5" required <?= $canRaise ? '' : 'disabled' ?>><?= e($_POST['description'] ?? '') ?></textarea>
        <label>Attachment (optional)</label>
        <input type="file" name="file" <?= $canRaise ? '' : 'disabled' ?>>
        <button type="submit" class="btn" <?= $canRaise ? '' : 'disabled' ?>>Submit Ticket</button>
    </form>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
