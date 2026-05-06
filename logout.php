<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /smartprint-workflow/signin.php');
    exit;
}

requireCsrf('/smartprint-workflow/dashboard.php');

session_unset();
session_destroy();

session_start();
setFlash('success', 'You have been logged out.');

header('Location: /smartprint-workflow/index.php');
exit;
