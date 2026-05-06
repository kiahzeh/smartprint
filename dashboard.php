<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
requireLogin();

$role = currentUser()['role'];

$routes = [
    'super_admin' => '/smartprint-workflow/pages/super_admin/dashboard.php',
    'admin' => '/smartprint-workflow/pages/admin/dashboard.php',
    'artist' => '/smartprint-workflow/pages/artist/dashboard.php',
    'client' => '/smartprint-workflow/pages/client/dashboard.php',
];

header('Location: ' . ($routes[$role] ?? '/smartprint-workflow/index.php'));
exit;
