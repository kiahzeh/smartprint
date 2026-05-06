<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['client']);

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/client/chat.php');

    $action = $_POST['action'] ?? '';
    $jobId = (int) ($_POST['job_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($action !== 'send_message') {
        setFlash('error', 'Invalid chat action.');
        header('Location: /smartprint-workflow/pages/client/chat.php');
        exit;
    }

    if ($jobId <= 0 || $message === '') {
        setFlash('error', 'Select a job and enter a message.');
        header('Location: /smartprint-workflow/pages/client/chat.php?job_id=' . $jobId);
        exit;
    }

    $accessStmt = $pdo->prepare("
        SELECT id
        FROM print_jobs
        WHERE id = ? AND client_id = ? AND assigned_artist_id IS NOT NULL
        LIMIT 1
    ");
    $accessStmt->execute([$jobId, $user['id']]);

    if (!$accessStmt->fetch()) {
        setFlash('error', 'You can only chat on jobs with an assigned artist.');
        header('Location: /smartprint-workflow/pages/client/chat.php');
        exit;
    }

    $insertStmt = $pdo->prepare('INSERT INTO job_messages (job_id, sender_id, message_text) VALUES (?, ?, ?)');
    $insertStmt->execute([$jobId, $user['id'], $message]);

    setFlash('success', 'Message sent.');
    header('Location: /smartprint-workflow/pages/client/chat.php?job_id=' . $jobId);
    exit;
}

$jobsStmt = $pdo->prepare("
    SELECT j.id, j.reference_number, j.title, j.updated_at, a.full_name AS artist_name
    FROM print_jobs j
    INNER JOIN users a ON a.id = j.assigned_artist_id
    WHERE j.client_id = ?
    ORDER BY j.updated_at DESC
");
$jobsStmt->execute([$user['id']]);
$jobs = $jobsStmt->fetchAll();

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
        SELECT m.id, m.message_text, m.created_at, m.sender_id,
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

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Artist Chat';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="card">
    <h2>Chat With Assigned Artist</h2>
    <p>Only jobs with assigned artists can be used for chat.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <div class="chat-layout">
        <aside class="chat-job-list">
            <h3>My Job Threads</h3>
            <?php if (!$jobs): ?>
                <p class="assignment-empty">No job threads yet.</p>
            <?php endif; ?>
            <?php foreach ($jobs as $job): ?>
                <a class="chat-job-link <?= (int) $job['id'] === $selectedJobId ? 'active' : '' ?>" href="/smartprint-workflow/pages/client/chat.php?job_id=<?= (int) $job['id'] ?>">
                    <strong><?= e($job['reference_number']) ?></strong>
                    <span><?= e($job['title']) ?></span>
                    <small>Artist: <?= e($job['artist_name']) ?></small>
                </a>
            <?php endforeach; ?>
        </aside>

        <div class="chat-thread-wrap">
            <?php if (!$selectedJob): ?>
                <p class="assignment-empty">Select a thread to view chat.</p>
            <?php else: ?>
                <div class="chat-thread-head">
                    <h3><?= e($selectedJob['reference_number']) ?> - <?= e($selectedJob['title']) ?></h3>
                    <p>Artist: <?= e($selectedJob['artist_name']) ?></p>
                </div>

                <div class="chat-thread">
                    <?php if (!$messages): ?>
                        <p class="assignment-empty">No messages yet. Start the conversation.</p>
                    <?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <?php $isMine = (int) $message['sender_id'] === (int) $user['id']; ?>
                        <article class="chat-bubble <?= $isMine ? 'mine' : 'other' ?>">
                            <div class="chat-meta">
                                <strong><?= e($message['sender_name']) ?></strong>
                                <span><?= e(strtoupper($message['sender_role'])) ?> | <?= e($message['created_at']) ?></span>
                            </div>
                            <p><?= nl2br(e($message['message_text'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="form-grid chat-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="job_id" value="<?= (int) $selectedJob['id'] ?>">
                    <label>Message
                        <textarea name="message" rows="3" maxlength="2000" required></textarea>
                    </label>
                    <button class="btn" type="submit">Send Message</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
