<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['artist']);

$user = currentUser();
$totalAssigned = $pdo->prepare('SELECT COUNT(*) FROM print_jobs WHERE assigned_artist_id = ?');
$totalAssigned->execute([$user['id']]);
$countAssigned = (int) $totalAssigned->fetchColumn();

$activeAssigned = $pdo->prepare("
    SELECT COUNT(*)
    FROM print_jobs j
    INNER JOIN job_statuses js ON js.id = j.status_id
    WHERE j.assigned_artist_id = ? AND js.code IN ('for_layout', 'for_approval')
");
$activeAssigned->execute([$user['id']]);
$countActive = (int) $activeAssigned->fetchColumn();

$pageTitle = 'Graphic Artist Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="grid-2">
    <article class="card stat"><h3>Assigned Jobs</h3><p><?= $countAssigned ?></p></article>
    <article class="card stat"><h3>Active Design Tasks</h3><p><?= $countActive ?></p></article>
</section>

<section class="card">
    <h2>Artist Workflow</h2>
    <p>Check your assigned jobs and move status as you complete each layout stage.</p>
    <a class="btn" href="/smartprint-workflow/pages/artist/jobs.php">Open Assigned Jobs</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/artist/chat.php">Open Client Chat</a>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
