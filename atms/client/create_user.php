<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);
requirePermission('users.manage');

$errors = [];

$rolesStmt = $pdo->query('SELECT id, name FROM roles ORDER BY is_protected DESC, name ASC');
$roles = $rolesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = (int) ($_POST['role_id'] ?? 0);

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($roleId <= 0) {
        $errors[] = 'Role assignment is mandatory.';
    } else {
        $roleCheck = $pdo->prepare('SELECT id, slug FROM roles WHERE id = :id LIMIT 1');
        $roleCheck->execute(['id' => $roleId]);
        $selectedRole = $roleCheck->fetch();
        if (!$selectedRole) {
            $errors[] = 'Selected role does not exist.';
        }
    }

    if (!$errors) {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $dup->execute(['email' => $email]);

        if ($dup->fetch()) {
            $errors[] = 'Email already exists.';
        } else {
            $insert = $pdo->prepare('INSERT INTO users (name, email, password, role, role_id) VALUES (:name, :email, :password, :role, :role_id)');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $selectedRole['slug'] === 'admin' ? 'admin' : 'client',
                'role_id' => $roleId,
            ]);
            redirect('/atms/client/users.php');
        }
    }
}

$pageTitle = 'Create User';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card form-card">
    <h2>Add User</h2>
    <?php foreach ($errors as $error): ?>
        <p class="alert-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Name</label>
        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Role <strong>(Mandatory)</strong></label>
        <select name="role_id" required>
            <option value="">Select role</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= ((int) ($_POST['role_id'] ?? 0) === (int) $role['id']) ? 'selected' : '' ?>>
                    <?= e($role['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn">Create User</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
