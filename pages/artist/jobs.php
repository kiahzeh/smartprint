<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['artist']);

$user = currentUser();
$statusRows = $pdo->query('SELECT id, code, label FROM job_statuses')->fetchAll();
$statusMap = [];
$statusLabelMap = [];
foreach ($statusRows as $statusRow) {
    $statusMap[$statusRow['code']] = (int) $statusRow['id'];
    $statusLabelMap[$statusRow['code']] = $statusRow['label'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/artist/jobs.php');

    $jobId = (int) ($_POST['job_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');
    $allowed = ['for_layout', 'for_approval', 'for_print', 'completed'];

    if ($jobId <= 0 || !in_array($status, $allowed, true) || !isset($statusMap[$status])) {
        setFlash('error', 'Invalid update request.');
    } else {
        $check = $pdo->prepare('SELECT id FROM print_jobs WHERE id = ? AND assigned_artist_id = ?');
        $check->execute([$jobId, $user['id']]);

        if (!$check->fetch()) {
            setFlash('error', 'Job not found for your account.');
        } else {
            $paymentSnapshot = fetchJobPaymentSnapshot($pdo, $jobId);
            if (!$paymentSnapshot || !$paymentSnapshot['is_met']) {
                setFlash('error', 'Client must complete at least 50% down payment before this job can continue.');
                header('Location: /smartprint-workflow/pages/artist/jobs.php');
                exit;
            }

            $update = $pdo->prepare('UPDATE print_jobs SET status_id = ? WHERE id = ?');
            $update->execute([$statusMap[$status], $jobId]);

            $log = $pdo->prepare('INSERT INTO job_updates (job_id, updated_by, status_id, note) VALUES (?, ?, ?, ?)');
            $log->execute([$jobId, $user['id'], $statusMap[$status], $note]);

            setFlash('success', 'Job updated successfully.');
        }
    }

    header('Location: /smartprint-workflow/pages/artist/jobs.php');
    exit;
}

$sql = "SELECT j.id, j.reference_number, j.title, j.description, st.name AS service_type, j.quantity, js.code AS status, j.due_date,
               c.full_name AS client_name
        FROM print_jobs j
        INNER JOIN service_types st ON st.id = j.service_type_id
        INNER JOIN job_statuses js ON js.id = j.status_id
        LEFT JOIN users c ON c.id = j.client_id
        WHERE j.assigned_artist_id = ?
        ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll();

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Assigned Jobs';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="card">
    <h2>Assigned Print Jobs</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Title</th><th>Client</th><th>Service</th><th>Qty</th><th>Status</th><th>Due Date</th><th>Update</th></tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><?= e($job['reference_number']) ?></td>
                        <td><?= e($job['title']) ?></td>
                        <td><?= e($job['client_name'] ?? '-') ?></td>
                        <td><?= e($job['service_type']) ?></td>
                        <td><?= (int) $job['quantity'] ?></td>
                        <td><span class="status status-<?= e($job['status']) ?>"><?= e(strtoupper($job['status'])) ?></span></td>
                        <td><?= e($job['due_date']) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                <select name="status" required>
                                    <?php foreach (['for_layout', 'for_approval', 'for_print', 'completed'] as $statusCode): ?>
                                        <?php if (!isset($statusLabelMap[$statusCode])) { continue; } ?>
                                        <option value="<?= e($statusCode) ?>" <?= $job['status'] === $statusCode ? 'selected' : '' ?>><?= e($statusLabelMap[$statusCode]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="note" placeholder="Design update">
                                <button class="btn small" type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
