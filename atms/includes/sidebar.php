<?php

declare(strict_types=1);

$role = $_SESSION['role'] ?? 'guest';
$currentPath = basename($_SERVER['PHP_SELF']);
?>
<div class="layout">
    <aside class="sidebar">
        <div>
            <h2>ATMS</h2>
            <p>Ticket System</p>
        </div>
        <nav>
            <?php if ($role === 'super_admin'): ?>
                <a class="<?= $currentPath === 'companies.php' ? 'active' : '' ?>" href="/atms/super_admin/companies.php">Companies</a>
            <?php elseif ($role === 'client_admin'): ?>
                <a class="<?= $currentPath === 'dashboard.php' ? 'active' : '' ?>" href="/atms/admin/dashboard.php">Dashboard</a>
                <a class="<?= $currentPath === 'tickets.php' ? 'active' : '' ?>" href="/atms/admin/tickets.php">Tickets</a>
            <?php elseif ($role === 'client'): ?>
                <a class="<?= $currentPath === 'dashboard.php' ? 'active' : '' ?>" href="/atms/client/dashboard.php">Dashboard</a>
                <a class="<?= $currentPath === 'raise_ticket.php' ? 'active' : '' ?>" href="/atms/client/raise_ticket.php">Raise Ticket</a>
                <a class="<?= $currentPath === 'my_tickets.php' ? 'active' : '' ?>" href="/atms/client/my_tickets.php">My Tickets</a>
            <?php endif; ?>
            <a href="/atms/auth/logout.php">Logout</a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1><?= e($pageTitle ?? 'ATMS') ?></h1>
            <div class="user-pill"><?= e($_SESSION['name'] ?? 'Guest') ?></div>
        </header>
