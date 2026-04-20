<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($token === '' || strlen($password) < 8) {
        $error = 'Valid token and minimum 8-char password required.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token AND used_at IS NULL AND expires_at >= NOW() LIMIT 1');
        $stmt->execute(['token' => $token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid or expired token.';
        } else {
            $pdo->beginTransaction();
            try {
                $updateUser = $pdo->prepare('UPDATE users SET password = :password, must_reset_password = 0 WHERE id = :id');
                $updateUser->execute([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) $reset['user_id'],
                ]);

                $useToken = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
                $useToken->execute(['id' => (int) $reset['id']]);
                $pdo->commit();
                $success = 'Password reset successful. You can login now.';
            } catch (Throwable $throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Password reset failed.';
            }
        }
    }
}

$pageTitle = 'Reset Password with Token';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>Reset Password</h2>
        <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="alert-success"><?= e($success) ?></p><?php endif; ?>
        <label>Reset Token</label>
        <input type="text" name="token" required>
        <label>New Password</label>
        <input type="password" name="password" required>
        <button class="btn" type="submit">Reset Password</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
