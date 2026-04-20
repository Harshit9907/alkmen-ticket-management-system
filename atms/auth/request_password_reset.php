<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(20));
            $ins = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
            $ins->execute(['user_id' => (int) $user['id'], 'token' => $token]);
            $message = 'Reset token (demo): ' . $token;
        }
    }

    if ($message === '') {
        $message = 'If account exists, reset token was generated.';
    }
}

$pageTitle = 'Request Password Reset';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>Forgot Password</h2>
        <?php if ($message): ?><p class="alert-success"><?= e($message) ?></p><?php endif; ?>
        <label>Email</label>
        <input type="email" name="email" required>
        <button class="btn" type="submit">Generate Reset Token</button>
        <p><a href="/atms/auth/password_reset.php">Already have token? Reset here</a></p>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
