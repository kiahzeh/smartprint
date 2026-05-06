<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['super_admin']);

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$jobCount = (int) $pdo->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn();
$activeArtists = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'artist' AND u.is_active = 1
")->fetchColumn();
$pageTitle = 'Super Admin Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="grid-3">
    <article class="card stat"><h3>Total Users</h3><p><?= $userCount ?></p></article>
    <article class="card stat"><h3>Total Jobs</h3><p><?= $jobCount ?></p></article>
    <article class="card stat"><h3>Active Artists</h3><p><?= $activeArtists ?></p></article>
</section>

<section class="card">
    <h2>System Control</h2>
    <p>Manage all users and monitor every print job in one place.</p>
    <a class="btn" href="/smartprint-workflow/pages/super_admin/users.php">Manage Users</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/super_admin/jobs.php">View All Jobs</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/super_admin/reports.php">Generate Reports</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/super_admin/chat.php">Open Chat Monitor</a>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
