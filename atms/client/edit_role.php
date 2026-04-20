<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);
requirePermission('roles.manage');

$roleId = (int) ($_GET['id'] ?? 0);
$errors = [];
$permissionCatalog = permissionCatalog();

$roleStmt = $pdo->prepare('SELECT id, name, slug, is_protected FROM roles WHERE id = :id LIMIT 1');
$roleStmt->execute(['id' => $roleId]);
$role = $roleStmt->fetch();

if (!$role) {
    redirect('/atms/client/roles.php');
}

$currentPermStmt = $pdo->prepare('SELECT permission_key FROM role_permissions WHERE role_id = :role_id');
$currentPermStmt->execute(['role_id' => $roleId]);
$currentPermissions = array_map(static fn (array $row): string => $row['permission_key'], $currentPermStmt->fetchAll());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $permissions = array_values(array_unique(array_filter($_POST['permissions'] ?? [], static fn ($permission): bool => isset($permissionCatalog[$permission]))));

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Role name must be at least 2 characters.';
    }

    if (count($permissions) === 0) {
        $errors[] = 'Select at least one permission.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE roles SET name = :name WHERE id = :id');
        $update->execute(['name' => $name, 'id' => $roleId]);

        $deletePerms = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
        $deletePerms->execute(['role_id' => $roleId]);

        $insertPermission = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_key) VALUES (:role_id, :permission_key)');
        foreach ($permissions as $permission) {
            $insertPermission->execute(['role_id' => $roleId, 'permission_key' => $permission]);
        }
        $pdo->commit();

        if ((int) $_SESSION['user_id'] > 0) {
            refreshSessionAuth($pdo, (int) $_SESSION['user_id']);
        }

        redirect('/atms/client/roles.php');
    }

    $currentPermissions = $permissions;
    $role['name'] = $name;
}

$pageTitle = 'Edit Role';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card form-card">
    <h2>Edit Role</h2>
    <?php if ((int) $role['is_protected'] === 1): ?>
        <p class="muted">This is a protected role. You can update permissions but cannot delete it.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Role Name</label>
        <input type="text" name="name" value="<?= e($role['name']) ?>" required>

        <label>Permissions</label>
        <table>
            <thead><tr><th>Permission</th><th>Allow</th></tr></thead>
            <tbody>
            <?php foreach ($permissionCatalog as $key => $label): ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= in_array($key, $currentPermissions, true) ? 'checked' : '' ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn">Save Changes</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
