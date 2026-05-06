<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['client']);

$user = currentUser();
$myJobs = $pdo->prepare('SELECT COUNT(*) FROM print_jobs WHERE client_id = ?');
$myJobs->execute([$user['id']]);
$countMyJobs = (int) $myJobs->fetchColumn();

$inProgress = $pdo->prepare("
    SELECT COUNT(*)
    FROM print_jobs j
    INNER JOIN job_statuses js ON js.id = j.status_id
    WHERE j.client_id = ? AND js.code IN ('for_layout', 'for_approval', 'for_print')
");
$inProgress->execute([$user['id']]);
$countInProgress = (int) $inProgress->fetchColumn();

$pageTitle = 'Client Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="grid-2">
    <article class="card stat"><h3>My Orders</h3><p><?= $countMyJobs ?></p></article>
    <article class="card stat"><h3>In Progress</h3><p><?= $countInProgress ?></p></article>
</section>

<section class="card">
    <h2>Client Requests</h2>
    <p>Submit new print requests and monitor progress updates from admin and artists.</p>
    <a class="btn" href="/smartprint-workflow/pages/client/jobs.php">Open My Jobs</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/client/chat.php">Open Artist Chat</a>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
