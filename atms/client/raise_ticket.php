<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $errors[] = 'Invalid file or file upload failed. Allowed: jpg, jpeg, png, pdf, txt, doc, docx.';
    if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'doc', 'docx'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Unsupported file type.';
        } else {
            $uploadName = uniqid('ticket_', true) . '.' . $ext;
            $destination = __DIR__ . '/../assets/uploads/' . $uploadName;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
                $errors[] = 'File upload failed.';
            }
        }
    }

    if (!$errors) {
        $ticketId = generateTicketId($pdo);
        $ticketStmt = $pdo->prepare(
            'INSERT INTO tickets (ticket_id, user_id, subject, description, category, priority, status) 
             VALUES (:ticket_id, :user_id, :subject, :description, :category, :priority, :status)'
        );
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
        if ($uploadName !== null) {
            $messageStmt = $pdo->prepare('INSERT INTO messages (ticket_id, sender_id, message, file) VALUES (:ticket_id, :sender_id, :message, :file)');
            $messageStmt->execute([
                'ticket_id' => (int) $pdo->lastInsertId(),
                'sender_id' => (int) $_SESSION['user_id'],
                'message' => 'Attachment uploaded with ticket creation.',
                'file' => $uploadName,
            ]);
        }

        $success = 'Ticket created successfully.';
    }
}

$pageTitle = 'Raise Ticket';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card form-card">
    <h2>Raise a New Ticket</h2>
    <?php foreach ($errors as $error): ?><p class="alert-error"><?= e($error) ?></p><?php endforeach; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Subject</label>
        <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" required>
        <label>Category</label>
        <select name="category" required>
            <option value="General">General</option>
            <option value="Technical">Technical</option>
            <option value="Billing">Billing</option>
            <option value="Account">Account</option>
        </select>
        <label>Priority</label>
        <select name="priority" required>
    <?php if ($success): ?><p class="alert-success"><?= e($success) ?></p><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Subject</label>
        <input type="text" name="subject" required>
        <label>Description</label>
        <textarea name="description" rows="5" required></textarea>
        <label>Category</label>
        <select name="category">
            <option>General</option>
            <option>Technical</option>
            <option>Billing</option>
            <option>Account</option>
        </select>
        <label>Priority</label>
        <select name="priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
        <label>Description</label>
        <textarea name="description" rows="5" required><?= e($_POST['description'] ?? '') ?></textarea>
        <label>Attachment (optional)</label>
        <label>File</label>
        <input type="file" name="file">
        <button type="submit" class="btn">Submit Ticket</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
