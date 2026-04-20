<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    $target = match ($_SESSION['role']) {
        'super_admin' => '/atms/super_admin/companies.php',
        'client_admin' => '/atms/admin/dashboard.php',
        default => '/atms/client/dashboard.php',
    };
    $target = in_array($_SESSION['role'], ['admin', 'super_admin'], true)
        ? '/atms/admin/dashboard.php'
        : '/atms/client/dashboard.php';
    redirect($target);
}

$pageTitle = 'ATMS Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-container">
    <div class="card auth-card">
        <h2>Welcome to ATMS</h2>
        <p>Login to continue.</p>
        <a class="btn" href="/atms/auth/login.php">Login</a>
        <a class="btn btn-outline" href="/atms/auth/register.php">Register (Invite Only)</a>
        <a class="btn" href="/atms/auth/register.php">Create Account</a>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
