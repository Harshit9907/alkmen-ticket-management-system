<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
requireRole(['client']);
requirePermission('roles.manage');

$errors = [];
$permissionCatalog = permissionCatalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $permissions = array_values(array_unique(array_filter($_POST['permissions'] ?? [], static fn ($permission): bool => isset($permissionCatalog[$permission]))));

    if ($name === '' || strlen($name) < 2) {
        $errors[] = 'Role name must be at least 2 characters.';
    }

    if (count($permissions) === 0) {
        $errors[] = 'Select at least one permission.';
    }

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));

    if ($slug === '' || in_array($slug, ['admin', 'client'], true)) {
        $errors[] = 'Role name generates a reserved/invalid slug.';
    }

    if (!$errors) {
        $exists = $pdo->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $exists->execute(['slug' => $slug]);
        if ($exists->fetch()) {
            $errors[] = 'Role already exists.';
        } else {
            $pdo->beginTransaction();
            $insertRole = $pdo->prepare('INSERT INTO roles (name, slug, is_protected) VALUES (:name, :slug, 0)');
            $insertRole->execute(['name' => $name, 'slug' => $slug]);
            $roleId = (int) $pdo->lastInsertId();

            $insertPermission = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_key) VALUES (:role_id, :permission_key)');
            foreach ($permissions as $permission) {
                $insertPermission->execute(['role_id' => $roleId, 'permission_key' => $permission]);
            }
            $pdo->commit();
            redirect('/atms/client/roles.php');
        }
    }
}

$pageTitle = 'Create Role';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="card form-card">
    <h2>Add Role</h2>
    <?php foreach ($errors as $error): ?>
        <p class="alert-error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Role Name</label>
        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>

        <label>Permissions</label>
        <table>
            <thead><tr><th>Permission</th><th>Allow</th></tr></thead>
            <tbody>
            <?php foreach ($permissionCatalog as $key => $label): ?>
                <tr>
                    <td><?= e($label) ?></td>
                    <td><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= in_array($key, $_POST['permissions'] ?? [], true) ? 'checked' : '' ?>></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn">Create Role</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
