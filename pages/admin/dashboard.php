<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['admin']);

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/admin/dashboard.php');

    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $sendJson = static function (int $statusCode, array $payload): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    };

    $action = $_POST['action'] ?? '';

    if ($action !== 'assign_artist') {
        if ($isAjax) {
            $sendJson(422, ['ok' => false, 'message' => 'Invalid dashboard action.']);
        }
        setFlash('error', 'Invalid dashboard action.');
        header('Location: /smartprint-workflow/pages/admin/dashboard.php');
        exit;
    }

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $artistId = (int) ($_POST['artist_id'] ?? 0);

    if ($jobId <= 0 || $artistId <= 0) {
        if ($isAjax) {
            $sendJson(422, ['ok' => false, 'message' => 'Project and artist are required.']);
        }
        setFlash('error', 'Project and artist are required.');
        header('Location: /smartprint-workflow/pages/admin/dashboard.php');
        exit;
    }

    $artistStmt = $pdo->prepare("
        SELECT u.id, u.full_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = ? AND r.code = 'artist' AND u.is_active = 1
        LIMIT 1
    ");
    $artistStmt->execute([$artistId]);
    $artist = $artistStmt->fetch();

    if (!$artist) {
        if ($isAjax) {
            $sendJson(404, ['ok' => false, 'message' => 'Artist not found or inactive.']);
        }
        setFlash('error', 'Artist not found or inactive.');
        header('Location: /smartprint-workflow/pages/admin/dashboard.php');
        exit;
    }

    $jobStmt = $pdo->prepare("
        SELECT j.id, j.status_id
        FROM print_jobs j
        INNER JOIN job_statuses js ON js.id = j.status_id
        WHERE j.id = ? AND j.assigned_admin_id = ? AND js.code NOT IN ('completed', 'cancelled')
        LIMIT 1
    ");
    $jobStmt->execute([$jobId, $user['id']]);
    $job = $jobStmt->fetch();

    if (!$job) {
        if ($isAjax) {
            $sendJson(404, ['ok' => false, 'message' => 'Project not found for your account.']);
        }
        setFlash('error', 'Project not found for your account.');
        header('Location: /smartprint-workflow/pages/admin/dashboard.php');
        exit;
    }

    $assignStmt = $pdo->prepare('UPDATE print_jobs SET assigned_artist_id = ? WHERE id = ?');
    $assignStmt->execute([$artistId, $jobId]);

    $logStmt = $pdo->prepare('INSERT INTO job_updates (job_id, updated_by, status_id, note) VALUES (?, ?, ?, ?)');
    $logStmt->execute([$jobId, $user['id'], (int) $job['status_id'], 'Artist assigned: ' . $artist['full_name']]);

    if ($isAjax) {
        $sendJson(200, ['ok' => true, 'message' => 'Project assigned to ' . $artist['full_name'] . '.']);
    }

    setFlash('success', 'Project assigned successfully.');
    header('Location: /smartprint-workflow/pages/admin/dashboard.php');
    exit;
}

$myJobs = $pdo->prepare('SELECT COUNT(*) FROM print_jobs WHERE assigned_admin_id = ?');
$myJobs->execute([$user['id']]);
$myJobCount = (int) $myJobs->fetchColumn();

$pending = $pdo->prepare("
    SELECT COUNT(*)
    FROM print_jobs j
    INNER JOIN job_statuses js ON js.id = j.status_id
    WHERE j.assigned_admin_id = ? AND js.code IN ('pending', 'for_layout')
");
$pending->execute([$user['id']]);
$pendingCount = (int) $pending->fetchColumn();

$projectsStmt = $pdo->prepare("
    SELECT j.id, j.reference_number, j.title, st.name AS service_type, j.quantity, j.due_date, js.code AS status,
           ar.full_name AS artist_name
    FROM print_jobs j
    INNER JOIN service_types st ON st.id = j.service_type_id
    INNER JOIN job_statuses js ON js.id = j.status_id
    LEFT JOIN users ar ON ar.id = j.assigned_artist_id
    WHERE j.assigned_admin_id = ? AND js.code NOT IN ('completed', 'cancelled')
    ORDER BY j.due_date ASC, j.created_at DESC
");
$projectsStmt->execute([$user['id']]);
$projects = $projectsStmt->fetchAll();

$artistsStmt = $pdo->prepare("
    SELECT u.id, u.full_name,
           SUM(CASE WHEN j.id IS NOT NULL AND js.code NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS active_jobs
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN print_jobs j ON j.assigned_artist_id = u.id AND j.assigned_admin_id = ?
    LEFT JOIN job_statuses js ON js.id = j.status_id
    WHERE r.code = 'artist' AND u.is_active = 1
    GROUP BY u.id, u.full_name
    ORDER BY u.full_name ASC
");
$artistsStmt->execute([$user['id']]);
$artists = $artistsStmt->fetchAll();

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="grid-2">
    <article class="card stat"><h3>Jobs Managed</h3><p><?= $myJobCount ?></p></article>
    <article class="card stat"><h3>Pending/For Layout</h3><p><?= $pendingCount ?></p></article>
</section>

<section class="card">
    <h2>Assign Projects by Drag and Drop</h2>
    <p>Row 1 contains active projects. Drag a project card and drop it on an artist in row 2.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <div id="assignFeedback" class="assignment-feedback" hidden></div>

    <div class="assignment-board" id="assignmentBoard" data-csrf="<?= e(csrfToken()) ?>">
        <div class="assignment-row">
            <h3>Row 1: Projects</h3>
            <div class="assignment-project-row">
                <?php if (!$projects): ?>
                    <p class="assignment-empty">No active projects to assign.</p>
                <?php endif; ?>

                <?php foreach ($projects as $project): ?>
                    <article class="project-card" draggable="true" data-job-id="<?= (int) $project['id'] ?>">
                        <div class="project-card-head">
                            <strong><?= e($project['reference_number']) ?></strong>
                            <span class="status status-<?= e($project['status']) ?>"><?= e(strtoupper($project['status'])) ?></span>
                        </div>
                        <p class="project-title"><?= e($project['title']) ?></p>
                        <p class="project-meta"><?= e($project['service_type']) ?> | Qty <?= (int) $project['quantity'] ?> | Due <?= e($project['due_date']) ?></p>
                        <p class="project-artist">Assigned: <span><?= e($project['artist_name'] ?? 'Unassigned') ?></span></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="assignment-row">
            <h3>Row 2: Artists</h3>
            <div class="assignment-artist-row">
                <?php foreach ($artists as $artist): ?>
                    <section class="artist-lane" data-artist-id="<?= (int) $artist['id'] ?>" data-artist-name="<?= e($artist['full_name']) ?>">
                        <div class="artist-head">
                            <strong><?= e($artist['full_name']) ?></strong>
                            <span><?= (int) $artist['active_jobs'] ?> active</span>
                        </div>
                        <p>Drop project here</p>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="card">
    <h2>Admin Actions</h2>
    <p>Create jobs, assign artists, and update order progress.</p>
    <a class="btn" href="/smartprint-workflow/pages/admin/jobs.php">Go To Job Management</a>
    <a class="btn btn-outline" href="/smartprint-workflow/pages/admin/chat.php">Open Chat Monitor</a>
</section>

<script>
(() => {
    const board = document.getElementById('assignmentBoard');
    const feedback = document.getElementById('assignFeedback');
    if (!board || !feedback) {
        return;
    }

    const csrfToken = board.dataset.csrf;
    let draggedCard = null;

    const showFeedback = (type, message) => {
        feedback.hidden = false;
        feedback.className = 'alert assignment-feedback ' + (type === 'error' ? 'error' : 'success');
        feedback.textContent = message;
    };

    const cards = board.querySelectorAll('.project-card');
    cards.forEach((card) => {
        card.addEventListener('dragstart', () => {
            draggedCard = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });

    const lanes = board.querySelectorAll('.artist-lane');
    lanes.forEach((lane) => {
        lane.addEventListener('dragover', (event) => {
            event.preventDefault();
            lane.classList.add('drop-active');
        });

        lane.addEventListener('dragleave', () => {
            lane.classList.remove('drop-active');
        });

        lane.addEventListener('drop', async (event) => {
            event.preventDefault();
            lane.classList.remove('drop-active');

            if (!draggedCard) {
                return;
            }

            const jobId = draggedCard.dataset.jobId;
            const artistId = lane.dataset.artistId;
            const artistName = lane.dataset.artistName || 'Artist';

            if (!jobId || !artistId) {
                return;
            }

            try {
                const body = new URLSearchParams({
                    action: 'assign_artist',
                    job_id: jobId,
                    artist_id: artistId,
                    _csrf: csrfToken
                });

                const response = await fetch('/smartprint-workflow/pages/admin/dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Failed to assign project.');
                }

                const artistSlot = draggedCard.querySelector('.project-artist span');
                if (artistSlot) {
                    artistSlot.textContent = artistName;
                }

                showFeedback('success', data.message || 'Project assigned.');
            } catch (error) {
                showFeedback('error', error.message || 'Failed to assign project.');
            }
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
