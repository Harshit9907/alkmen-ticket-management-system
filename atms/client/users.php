<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);
requirePermission('users.manage');

$stmt = $pdo->query('SELECT u.id, u.name, u.email, u.created_at, COALESCE(r.name, u.role) AS role_name
                     FROM users u
                     LEFT JOIN roles r ON r.id = u.role_id
                     ORDER BY u.created_at DESC');
$users = $stmt->fetchAll();

$pageTitle = 'Users';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <div class="table-header">
        <h2>User Management</h2>
        <a class="btn" href="/atms/client/create_user.php">Add User</a>
    </div>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th></tr></thead>
        <tbody>
        <?php if (!$users): ?>
            <tr><td colspan="4" class="muted">No users found.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><?= e($user['role_name']) ?></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($user['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
