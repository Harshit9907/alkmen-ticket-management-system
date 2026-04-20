<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    redirect('/atms/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please provide valid credentials.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password, role, company_id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['company_id'] = (int) $user['company_id'];
            redirect('/atms/index.php');
        }

        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>Sign in</h2>
        <?php if ($error): ?><p class="alert-error"><?= e($error) ?></p><?php endif; ?>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" class="btn">Login</button>
        <p>No account? <a href="/atms/auth/register.php">Register</a></p>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
