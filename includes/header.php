<?php

declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? 'SmartPrint WorkFlow';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/smartprint-workflow/assets/css/styles.css">
</head>
<body>
<div class="macbook-shell">
    <div class="macbook-frame">
        <div class="macbook-screen">
            <header class="topbar">
                <div class="topbar-left">
                    <img src="/smartprint-workflow/assets/img/smartprint-logo.svg" alt="SmartPrint logo" class="brand-logo">
                    <div class="brand-block">
                        <h1>SmartPrint WorkFlow</h1>
                        <p>Printing Shop Operations System</p>
                    </div>
                </div>
                <div class="topbar-actions">
                    <button type="button" class="btn btn-outline" id="themeToggle">Dark mode</button>
                    <?php if ($user): ?>
                        <div class="user-info">
                            <span class="user-name"><?= e($user['full_name']) ?></span>
                            <span class="role-pill"><?= e(strtoupper($user['role'])) ?></span>
                            <form method="POST" action="/smartprint-workflow/logout.php" class="logout-form">
                                <?= csrfField() ?>
                                <button class="btn btn-outline" type="submit">Logout</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($user): ?>
            <nav class="nav">
                <a href="/smartprint-workflow/dashboard.php">Dashboard</a>
                <?php if ($user['role'] === 'super_admin'): ?>
                    <a href="/smartprint-workflow/pages/super_admin/users.php">Users</a>
                    <a href="/smartprint-workflow/pages/super_admin/jobs.php">All Jobs</a>
                    <a href="/smartprint-workflow/pages/super_admin/reports.php">Reports</a>
                    <a href="/smartprint-workflow/pages/super_admin/chat.php">Chat Monitor</a>
                <?php elseif ($user['role'] === 'admin'): ?>
                    <a href="/smartprint-workflow/pages/admin/jobs.php">Manage Jobs</a>
                    <a href="/smartprint-workflow/pages/admin/chat.php">Chat Monitor</a>
                <?php elseif ($user['role'] === 'artist'): ?>
                    <a href="/smartprint-workflow/pages/artist/jobs.php">Assigned Jobs</a>
                    <a href="/smartprint-workflow/pages/artist/chat.php">Client Chat</a>
                <?php elseif ($user['role'] === 'client'): ?>
                    <a href="/smartprint-workflow/pages/client/jobs.php">My Jobs</a>
                    <a href="/smartprint-workflow/pages/client/chat.php">Artist Chat</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

<main class="container">
