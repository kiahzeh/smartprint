<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (currentUser()) {
    header('Location: /smartprint-workflow/dashboard.php');
    exit;
}

$pageTitle = 'Welcome';
require_once __DIR__ . '/includes/header.php';
?>
<section class="card auth-card">
    <h2>SmartPrint Workflow</h2>
    <p>Manage print requests, assign design tasks, and track production progress in one system.</p>

    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px;">
        <a class="btn" href="/smartprint-workflow/signin.php">Login</a>
        <a class="btn btn-outline" href="/smartprint-workflow/signup.php">Sign Up</a>
        <a class="btn btn-outline" href="/smartprint-workflow/track.php">Track Job</a>
    </div>
</section>

<section class="card auth-card">
    <h3>What You Can Do</h3>
    <ul>
        <li>Clients can submit and monitor print requests.</li>
        <li>Admins can assign artists and update workflows.</li>
        <li>Artists can move jobs through design and print stages.</li>
        <li>Super admins can manage users and view reports.</li>
    </ul>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
