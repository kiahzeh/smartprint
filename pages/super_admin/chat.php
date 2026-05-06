<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['super_admin']);

$jobs = $pdo->query("
    SELECT j.id, j.reference_number, j.title,
           c.full_name AS client_name,
           a.full_name AS artist_name,
           ad.full_name AS admin_name,
           COUNT(m.id) AS message_count,
           MAX(m.created_at) AS last_message_at
    FROM print_jobs j
    INNER JOIN users c ON c.id = j.client_id
    INNER JOIN users a ON a.id = j.assigned_artist_id
    LEFT JOIN users ad ON ad.id = j.assigned_admin_id
    LEFT JOIN job_messages m ON m.job_id = j.id
    GROUP BY j.id, j.reference_number, j.title, c.full_name, a.full_name, ad.full_name
    ORDER BY COALESCE(MAX(m.created_at), j.updated_at) DESC
")->fetchAll();

$selectedJobId = (int) ($_GET['job_id'] ?? 0);
if ($selectedJobId <= 0 && isset($jobs[0])) {
    $selectedJobId = (int) $jobs[0]['id'];
}

$selectedJob = null;
foreach ($jobs as $job) {
    if ((int) $job['id'] === $selectedJobId) {
        $selectedJob = $job;
        break;
    }
}

$messages = [];
if ($selectedJob) {
    $messagesStmt = $pdo->prepare("
        SELECT m.id, m.message_text, m.created_at,
               u.full_name AS sender_name,
               r.code AS sender_role
        FROM job_messages m
        INNER JOIN users u ON u.id = m.sender_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE m.job_id = ?
        ORDER BY m.created_at ASC
    ");
    $messagesStmt->execute([(int) $selectedJob['id']]);
    $messages = $messagesStmt->fetchAll();
}

$pageTitle = 'Chat Monitor';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="card">
    <h2>System Chat Monitor</h2>
    <p>Read-only visibility of all client-artist job conversations.</p>

    <div class="chat-layout">
        <aside class="chat-job-list">
            <h3>All Threads</h3>
            <?php if (!$jobs): ?>
                <p class="assignment-empty">No threads found.</p>
            <?php endif; ?>
            <?php foreach ($jobs as $job): ?>
                <a class="chat-job-link <?= (int) $job['id'] === $selectedJobId ? 'active' : '' ?>" href="/smartprint-workflow/pages/super_admin/chat.php?job_id=<?= (int) $job['id'] ?>">
                    <strong><?= e($job['reference_number']) ?></strong>
                    <span><?= e($job['title']) ?></span>
                    <small><?= e($job['client_name']) ?> ↔ <?= e($job['artist_name']) ?></small>
                    <small>Admin: <?= e($job['admin_name'] ?? '-') ?></small>
                    <small><?= (int) $job['message_count'] ?> messages</small>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="chat-thread-wrap">
            <?php if (!$selectedJob): ?>
                <p class="assignment-empty">Select a thread to view messages.</p>
            <?php else: ?>
                <div class="chat-thread-head">
                    <h3><?= e($selectedJob['reference_number']) ?> - <?= e($selectedJob['title']) ?></h3>
                    <p><?= e($selectedJob['client_name']) ?> ↔ <?= e($selectedJob['artist_name']) ?> | Admin: <?= e($selectedJob['admin_name'] ?? '-') ?></p>
                </div>

                <div class="chat-thread">
                    <?php if (!$messages): ?>
                        <p class="assignment-empty">No messages yet for this job.</p>
                    <?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <article class="chat-bubble monitor">
                            <div class="chat-meta">
                                <strong><?= e($message['sender_name']) ?></strong>
                                <span><?= e(strtoupper($message['sender_role'])) ?> | <?= e($message['created_at']) ?></span>
                            </div>
                            <p><?= nl2br(e($message['message_text'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
