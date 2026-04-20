<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) {
    redirect('/atms/index.php');
}

syncSessionCompanyId($pdo);
