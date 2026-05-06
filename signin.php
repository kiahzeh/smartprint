<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (currentUser()) {
    header('Location: /smartprint-workflow/dashboard.php');
    exit;
}

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<section class="card auth-card">
    <h2>Login</h2>
    <p>Use your role account to enter SmartPrint WorkFlow.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="/smartprint-workflow/login.php" class="form-grid">
        <?= csrfField() ?>
        <label>Email
            <input type="email" name="email" required>
        </label>

        <label>Password
            <input type="password" name="password" required>
        </label>

        <button type="submit" class="btn">Login</button>
    </form>

    <p style="margin-top: 14px;">
        No account yet?
        <a href="/smartprint-workflow/signup.php">Create one here</a>.
    </p>

    <div class="demo-box">
        <h3>Demo Accounts</h3>
        <ul>
            <li>Super Admin: super@smartprint.com / super123</li>
            <li>Admin: admin@smartprint.com / admin123</li>
            <li>Artist: artist@smartprint.com / artist123</li>
            <li>Client: client@smartprint.com / client123</li>
        </ul>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
