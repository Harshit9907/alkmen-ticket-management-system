<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET password = :password, must_reset_password = 0 WHERE id = :id');
        $stmt->execute([
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'id' => currentUserId(),
        ]);
        redirect('/atms/index.php');
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>First Login: Reset Password</h2>
        <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
        <label>New Password</label>
        <input type="password" name="password" required>
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
        <button class="btn" type="submit">Update Password</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
