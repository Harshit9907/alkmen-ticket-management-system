<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/dashboard_scope.php';

redirect(currentDashboardRoute((string) $_SESSION['role']));
