<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['admin']);

$user = currentUser();
$statusRows = $pdo->query('SELECT id, code, label FROM job_statuses ORDER BY id ASC')->fetchAll();
$statusMap = [];
$statusLabelMap = [];
foreach ($statusRows as $statusRow) {
    $statusMap[$statusRow['code']] = (int) $statusRow['id'];
    $statusLabelMap[$statusRow['code']] = $statusRow['label'];
}
$serviceTypes = $pdo->query('SELECT id, name FROM service_types WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/admin/jobs.php');

    $action = $_POST['action'] ?? '';

    if ($action === 'create_job') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $artistId = (int) ($_POST['artist_id'] ?? 0);
        $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $totalAmount = (float) ($_POST['total_amount'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $dueDate = $_POST['due_date'] ?? '';

        if ($clientId <= 0 || $artistId <= 0 || $serviceTypeId <= 0 || $title === '' || $totalAmount <= 0 || $quantity <= 0 || $dueDate === '') {
            setFlash('error', 'Please fill all required job fields.');
        } elseif (!isset($statusMap['pending'])) {
            setFlash('error', 'Job statuses are not configured correctly.');
        } else {
            $refNumber = generateReferenceNumber($pdo);
            $stmt = $pdo->prepare('INSERT INTO print_jobs (reference_number, client_id, assigned_admin_id, assigned_artist_id, title, description, service_type_id, total_amount, quantity, due_date, status_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$refNumber, $clientId, $user['id'], $artistId, $title, $description, $serviceTypeId, $totalAmount, $quantity, $dueDate, $statusMap['pending']]);
            setFlash('success', 'Print job created successfully.');
        }
    }

    if ($action === 'update_status') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $note = trim($_POST['note'] ?? '');
        $allowed = ['pending', 'for_layout', 'for_approval', 'for_print', 'completed', 'cancelled'];

        if ($jobId <= 0 || !in_array($newStatus, $allowed, true) || !isset($statusMap[$newStatus])) {
            setFlash('error', 'Invalid job update request.');
        } else {
            $ownedJob = $pdo->prepare('SELECT id FROM print_jobs WHERE id = ? AND assigned_admin_id = ?');
            $ownedJob->execute([$jobId, $user['id']]);

            if (!$ownedJob->fetch()) {
                setFlash('error', 'Job not found for your account.');
            } else {
                $needsDownPayment = in_array($newStatus, ['for_layout', 'for_approval', 'for_print', 'completed'], true);
                if ($needsDownPayment) {
                    $paymentSnapshot = fetchJobPaymentSnapshot($pdo, $jobId);
                    if (!$paymentSnapshot || !$paymentSnapshot['is_met']) {
                        setFlash('error', 'Client must complete at least 50% down payment before starting this job.');
                        header('Location: /smartprint-workflow/pages/admin/jobs.php');
                        exit;
                    }
                }

                $update = $pdo->prepare('UPDATE print_jobs SET status_id = ? WHERE id = ?');
                $update->execute([$statusMap[$newStatus], $jobId]);

                $log = $pdo->prepare('INSERT INTO job_updates (job_id, updated_by, status_id, note) VALUES (?, ?, ?, ?)');
                $log->execute([$jobId, $user['id'], $statusMap[$newStatus], $note]);
                setFlash('success', 'Job status updated.');
            }
        }
    }

    header('Location: /smartprint-workflow/pages/admin/jobs.php');
    exit;
}

$clients = $pdo->query("
    SELECT u.id, u.full_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'client' AND u.is_active = 1
    ORDER BY u.full_name
")->fetchAll();
$artists = $pdo->query("
    SELECT u.id, u.full_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE r.code = 'artist' AND u.is_active = 1
    ORDER BY u.full_name
")->fetchAll();

$sql = "SELECT j.id, j.reference_number, j.title, st.name AS service_type, j.total_amount, j.quantity, js.code AS status, j.due_date,
               COALESCE(pay.total_paid, 0) AS total_paid,
               c.full_name AS client_name,
               g.full_name AS artist_name
        FROM print_jobs j
        INNER JOIN service_types st ON st.id = j.service_type_id
        INNER JOIN job_statuses js ON js.id = j.status_id
        LEFT JOIN (
            SELECT job_id, SUM(amount) AS total_paid
            FROM job_payments
            GROUP BY job_id
        ) pay ON pay.job_id = j.id
        LEFT JOIN users c ON c.id = j.client_id
        LEFT JOIN users g ON g.id = j.assigned_artist_id
        WHERE j.assigned_admin_id = ?
        ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll();

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Manage Jobs';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="card">
    <h2>Create Print Job</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_job">
        <label>Client
            <select name="client_id" required>
                <option value="">Select Client</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Graphic Artist
            <select name="artist_id" required>
                <option value="">Select Artist</option>
                <?php foreach ($artists as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"><?= e($a['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Job Title
            <input type="text" name="title" required>
        </label>
        <label>Service Type
            <select name="service_type_id" required>
                <option value="">Select Service Type</option>
                <?php foreach ($serviceTypes as $serviceType): ?>
                    <option value="<?= (int) $serviceType['id'] ?>"><?= e($serviceType['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Quantity
            <input type="number" name="quantity" min="1" required>
        </label>
        <label>Total Amount (PHP)
            <input type="number" name="total_amount" min="1" step="0.01" required>
        </label>
        <label>Due Date
            <input type="date" name="due_date" required>
        </label>
        <label>Description
            <textarea name="description" rows="4"></textarea>
        </label>
        <button class="btn" type="submit">Create Job</button>
    </form>
</section>

<section class="card">
    <h2>My Managed Jobs</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Title</th><th>Client</th><th>Artist</th><th>Service</th><th>Qty</th><th>Down Payment</th><th>Status</th><th>Due</th><th>Update</th></tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><?= e($job['reference_number']) ?></td>
                    <td><?= e($job['title']) ?></td>
                    <td><?= e($job['client_name'] ?? '-') ?></td>
                    <td><?= e($job['artist_name'] ?? '-') ?></td>
                    <td><?= e($job['service_type']) ?></td>
                    <td><?= (int) $job['quantity'] ?></td>
                    <td>
                        <?php
                        $requiredDown = ((float) $job['total_amount']) * 0.5;
                        $totalPaid = (float) $job['total_paid'];
                        ?>
                        <small>PHP <?= number_format($totalPaid, 2) ?> / <?= number_format($requiredDown, 2) ?></small>
                    </td>
                    <td><span class="status status-<?= e($job['status']) ?>"><?= e(strtoupper($job['status'])) ?></span></td>
                    <td><?= e($job['due_date']) ?></td>
                    <td>
                        <form method="POST" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                            <select name="status" required>
                                <?php foreach (['pending', 'for_layout', 'for_approval', 'for_print', 'completed', 'cancelled'] as $statusCode): ?>
                                    <?php if (!isset($statusLabelMap[$statusCode])) { continue; } ?>
                                    <option value="<?= e($statusCode) ?>" <?= $job['status'] === $statusCode ? 'selected' : '' ?>><?= e($statusLabelMap[$statusCode]) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="note" placeholder="Update note">
                            <button class="btn small" type="submit">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
