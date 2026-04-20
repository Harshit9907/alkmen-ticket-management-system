<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['super_admin']);

$errors = [];
$credentials = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $companyCode = strtoupper(trim($_POST['company_code'] ?? ''));
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');

    if ($companyName === '') {
        $errors[] = 'Company name is required.';
    }
    if ($companyCode === '' || !preg_match('/^[A-Z0-9_\-]{3,20}$/', $companyCode)) {
        $errors[] = 'Company code must be 3-20 chars (A-Z, 0-9, -, _).';
    }
    if ($adminName === '') {
        $errors[] = 'Initial client admin name is required.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Initial client admin email is invalid.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $checkCompany = $pdo->prepare('SELECT id FROM companies WHERE code = :code OR name = :name LIMIT 1');
            $checkCompany->execute(['code' => $companyCode, 'name' => $companyName]);
            if ($checkCompany->fetch()) {
                throw new RuntimeException('Company name/code already exists.');
            }

            $checkUser = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $checkUser->execute(['email' => $adminEmail]);
            if ($checkUser->fetch()) {
                throw new RuntimeException('Initial admin email already exists.');
            }

            $insertCompany = $pdo->prepare('INSERT INTO companies (name, code) VALUES (:name, :code)');
            $insertCompany->execute(['name' => $companyName, 'code' => $companyCode]);
            $companyId = (int) $pdo->lastInsertId();

            $insertRole1 = $pdo->prepare("INSERT INTO roles (company_id, key_name, label, is_system) VALUES (:company_id, 'client_admin', 'Client Admin', 1)");
            $insertRole1->execute(['company_id' => $companyId]);
            $clientAdminRoleId = (int) $pdo->lastInsertId();

            $insertRole2 = $pdo->prepare("INSERT INTO roles (company_id, key_name, label, is_system) VALUES (:company_id, 'client_user', 'Client User', 1)");
            $insertRole2->execute(['company_id' => $companyId]);
            $clientUserRoleId = (int) $pdo->lastInsertId();

            $permIds = $pdo->query("SELECT id, key_name FROM permissions WHERE key_name IN ('tickets.view','tickets.manage','users.manage')")
                ->fetchAll();
            $permMap = [];
            foreach ($permIds as $perm) {
                $permMap[$perm['key_name']] = (int) $perm['id'];
            }

            $rp = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
            foreach (['tickets.view', 'tickets.manage', 'users.manage'] as $key) {
                $rp->execute(['role_id' => $clientAdminRoleId, 'permission_id' => $permMap[$key]]);
            }
            $rp->execute(['role_id' => $clientUserRoleId, 'permission_id' => $permMap['tickets.view']]);

            $tempPassword = bin2hex(random_bytes(5)) . 'A!';
            $insertUser = $pdo->prepare('INSERT INTO users (company_id, name, email, password, role, must_reset_password) VALUES (:company_id, :name, :email, :password, :role, 1)');
            $insertUser->execute([
                'company_id' => $companyId,
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => password_hash($tempPassword, PASSWORD_DEFAULT),
                'role' => 'client_admin',
            ]);

            $pdo->commit();
            $credentials = [
                'email' => $adminEmail,
                'temp_password' => $tempPassword,
                'company_code' => $companyCode,
            ];
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $throwable->getMessage();
        }
    }
}

$companies = $pdo->query(
    "SELECT c.id, c.name, c.code, c.created_at,
            SUM(CASE WHEN u.role = 'client_admin' THEN 1 ELSE 0 END) AS admin_count,
            SUM(CASE WHEN u.role = 'client' THEN 1 ELSE 0 END) AS client_count
     FROM companies c
     LEFT JOIN users u ON u.company_id = c.id
     GROUP BY c.id
     ORDER BY c.created_at DESC"
)->fetchAll();

$pageTitle = 'Super Admin - Companies';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <h2>Create Company + Initial Client Admin</h2>
    <?php foreach ($errors as $error): ?><p class="alert-error"><?= e($error) ?></p><?php endforeach; ?>
    <?php if ($credentials): ?>
        <p class="alert-success">Provisioning completed successfully.</p>
        <p><strong>Initial Credentials:</strong> <?= e($credentials['email']) ?> / <?= e($credentials['temp_password']) ?></p>
        <p class="muted">Share this securely. User must reset password on first login.</p>
    <?php endif; ?>

    <form method="POST">
        <label>Company Name</label>
        <input type="text" name="company_name" required>
        <label>Company Code</label>
        <input type="text" name="company_code" required>
        <label>Initial Client Admin Name</label>
        <input type="text" name="admin_name" required>
        <label>Initial Client Admin Email</label>
        <input type="email" name="admin_email" required>
        <button class="btn" type="submit">Provision Company</button>
    </form>
</div>

<div class="card mt-16">
    <h2>Companies</h2>
    <table>
        <thead><tr><th>Name</th><th>Code</th><th>Admins</th><th>Clients</th><th>Created</th></tr></thead>
        <tbody>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= e($company['name']) ?></td>
                    <td><?= e($company['code']) ?></td>
                    <td><?= (int) $company['admin_count'] ?></td>
                    <td><?= (int) $company['client_count'] ?></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($company['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
