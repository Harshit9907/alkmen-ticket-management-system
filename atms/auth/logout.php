<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

session_unset();
session_destroy();
redirect('/atms/index.php');
