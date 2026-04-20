<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    redirect('/atms/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'client') === 'admin' ? 'admin' : 'client';

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);

        if ($check->fetch()) {
            $errors[] = 'Email already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'role' => 'client',
                'role' => $role,
            ]);
            redirect('/atms/auth/login.php');
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>Create account</h2>
        <?php foreach ($errors as $error): ?>
            <p class="alert-error"><?= e($error) ?></p>
        <?php endforeach; ?>
        <label>Name</label>
        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <input type="text" name="name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <label>Role</label>
        <select name="role">
            <option value="client">Client</option>
            <option value="admin">Admin</option>
        </select>
        <button type="submit" class="btn">Register</button>
        <p>Have an account? <a href="/atms/auth/login.php">Login</a></p>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
