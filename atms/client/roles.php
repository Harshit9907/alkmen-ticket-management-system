<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);
requirePermission('roles.manage');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $roleId = (int) ($_POST['role_id'] ?? 0);

    $roleStmt = $pdo->prepare('SELECT id, name, is_protected FROM roles WHERE id = :id LIMIT 1');
    $roleStmt->execute(['id' => $roleId]);
    $role = $roleStmt->fetch();

    if (!$role) {
        $errors[] = 'Role not found.';
    } elseif ((int) $role['is_protected'] === 1) {
        $errors[] = 'Protected roles cannot be deleted.';
    } else {
        $inUseStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :role_id');
        $inUseStmt->execute(['role_id' => $roleId]);
        $inUseCount = (int) $inUseStmt->fetchColumn();

        if ($inUseCount > 0) {
            $errors[] = 'Role is currently assigned to users and cannot be deleted.';
        } else {
            $deleteStmt = $pdo->prepare('DELETE FROM roles WHERE id = :id');
            $deleteStmt->execute(['id' => $roleId]);
            redirect('/atms/client/roles.php');
        }
    }
}

$stmt = $pdo->query('SELECT r.id, r.name, r.slug, r.is_protected,
                            (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count
                     FROM roles r
                     ORDER BY r.is_protected DESC, r.name ASC');
$roles = $stmt->fetchAll();

$pageTitle = 'Roles Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card">
    <div class="table-header">
        <h2>Roles</h2>
        <a class="btn" href="/atms/client/create_role.php">Add Role</a>
    </div>

    <?php foreach ($errors as $error): ?>
        <p class="alert-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <table>
        <thead><tr><th>Name</th><th>Slug</th><th>Users</th><th>Type</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $role): ?>
            <tr>
                <td><?= e($role['name']) ?></td>
                <td><?= e($role['slug']) ?></td>
                <td><?= (int) $role['user_count'] ?></td>
                <td><?= (int) $role['is_protected'] === 1 ? 'Protected' : 'Custom' ?></td>
                <td>
                    <a href="/atms/client/edit_role.php?id=<?= (int) $role['id'] ?>">Edit</a>
                    <?php if ((int) $role['is_protected'] !== 1): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this role?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
                            <button type="submit" class="btn btn-outline">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
