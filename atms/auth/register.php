<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    redirect('/atms/index.php');
}

$errors = [];
$allowedRoles = ['employee', 'manager', 'client_admin', 'super_admin'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';

    if (!in_array($role, $allowedRoles, true)) {
        $role = 'employee';
    }

    if ($token === '') {
        $errors[] = 'Invitation token is required.';
    }
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
        $inviteStmt = $pdo->prepare('SELECT * FROM invitations WHERE token = :token AND used_at IS NULL AND expires_at >= NOW() LIMIT 1');
        $inviteStmt->execute(['token' => $token]);
        $invite = $inviteStmt->fetch();

        if (!$invite) {
            $errors[] = 'Invalid or expired invitation token.';
        } elseif (strtolower($invite['email']) !== strtolower($email)) {
            $errors[] = 'Invitation token does not match this email.';
        } else {
            $companyStmt = $pdo->query("SELECT id FROM companies WHERE status = 'active' ORDER BY id ASC LIMIT 1");
            $companyId = (int) ($companyStmt->fetchColumn() ?: 0);

            if ($companyId === 0) {
                $errors[] = 'No active company available for registration.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (name, email, password, role, company_id) VALUES (:name, :email, :password, :role, :company_id)');
                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'client',
                    'company_id' => $companyId,
                ]);
                redirect('/atms/auth/login.php');
            }
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $errors[] = 'Email already exists.';
            } else {
                $insert = $pdo->prepare('INSERT INTO users (company_id, name, email, password, role, must_reset_password) VALUES (:company_id, :name, :email, :password, :role, 0)');
                $insert->execute([
                    'company_id' => (int) $invite['company_id'],
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $invite['role'],
                ]);

                $consume = $pdo->prepare('UPDATE invitations SET used_at = NOW() WHERE id = :id');
                $consume->execute(['id' => (int) $invite['id']]);

                $success = 'Registration complete. You can login now.';
            }
            $insert = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'role' => $role,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'client',
            ]);
            redirect('/atms/auth/login.php');
        }
    }
}

$pageTitle = 'Register (Invitation Only)';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-container">
    <form method="POST" class="card auth-card">
        <h2>Register (Invitation Only)</h2>
        <p class="muted">Public signup is disabled. Ask your admin for an invite token.</p>
        <?php foreach ($errors as $error): ?><p class="alert-error"><?= e($error) ?></p><?php endforeach; ?>
        <?php if ($success): ?><p class="alert-success"><?= e($success) ?></p><?php endif; ?>
        <label>Invitation Token</label>
        <input type="text" name="token" value="<?= e($_POST['token'] ?? '') ?>" required>
        <label>Name</label>
        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <label>Role</label>
        <select name="role">
            <option value="employee">Employee</option>
            <option value="manager">Manager</option>
            <option value="client_admin">Client Admin</option>
            <option value="super_admin">Super Admin</option>
        </select>
        <button type="submit" class="btn">Complete Registration</button>
        <button type="submit" class="btn">Register</button>
        <p>Have an account? <a href="/atms/auth/login.php">Login</a></p>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
