<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (currentUser()) {
    header('Location: /smartprint-workflow/dashboard.php');
    exit;
}

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Sign Up';
require_once __DIR__ . '/includes/header.php';
?>
<section class="card auth-card">
    <h2>Create Client Account</h2>
    <p>Register a client account to submit and track print requests.</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="/smartprint-workflow/register.php" class="form-grid">
        <?= csrfField() ?>
        <label>Full Name
            <input type="text" name="full_name" required>
        </label>

        <label>Email
            <input type="email" name="email" required>
        </label>

        <label>Password
            <input type="password" name="password" minlength="8" required>
        </label>

        <label>Confirm Password
            <input type="password" name="confirm_password" minlength="8" required>
        </label>

        <button type="submit" class="btn">Create Account</button>
    </form>

    <p style="margin-top: 14px;">
        Already have an account?
        <a href="/smartprint-workflow/signin.php">Login here</a>.
    </p>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
