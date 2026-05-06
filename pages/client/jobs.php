<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['client']);

$user = currentUser();
$statusRows = $pdo->query('SELECT id, code FROM job_statuses')->fetchAll();
$statusMap = [];
foreach ($statusRows as $statusRow) {
    $statusMap[$statusRow['code']] = (int) $statusRow['id'];
}
$serviceTypes = $pdo->query('SELECT id, name FROM service_types WHERE is_active = 1 ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/client/jobs.php');

    $action = $_POST['action'] ?? 'create_job';

    if ($action === 'create_job') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $serviceTypeId = (int) ($_POST['service_type_id'] ?? 0);
        $totalAmount = (float) ($_POST['total_amount'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $dueDate = $_POST['due_date'] ?? '';

        if ($title === '' || $serviceTypeId <= 0 || $totalAmount <= 0 || $quantity <= 0 || $dueDate === '') {
            setFlash('error', 'Please complete all required request fields.');
        } elseif (!isset($statusMap['pending'])) {
            setFlash('error', 'Job statuses are not configured correctly.');
        } else {
            $refNumber = generateReferenceNumber($pdo);
            $stmt = $pdo->prepare('INSERT INTO print_jobs (reference_number, client_id, title, description, service_type_id, total_amount, quantity, due_date, status_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$refNumber, $user['id'], $title, $description, $serviceTypeId, $totalAmount, $quantity, $dueDate, $statusMap['pending']]);
            setFlash('success', 'Your request has been submitted with reference: ' . $refNumber);
        }
    } elseif ($action === 'pay_down_payment') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');

        if ($jobId <= 0 || $amount <= 0) {
            setFlash('error', 'Please provide a valid payment amount.');
        } else {
            $jobStmt = $pdo->prepare("
                SELECT j.id
                FROM print_jobs j
                INNER JOIN job_statuses js ON js.id = j.status_id
                WHERE j.id = ? AND j.client_id = ? AND js.code NOT IN ('completed', 'cancelled')
                LIMIT 1
            ");
            $jobStmt->execute([$jobId, $user['id']]);

            if (!$jobStmt->fetch()) {
                setFlash('error', 'Job not found for your account.');
            } else {
                $payStmt = $pdo->prepare('INSERT INTO job_payments (job_id, paid_by, amount, note) VALUES (?, ?, ?, ?)');
                $payStmt->execute([$jobId, $user['id'], $amount, $note]);

                $snapshot = fetchJobPaymentSnapshot($pdo, $jobId);
                if ($snapshot && $snapshot['is_met']) {
                    setFlash('success', 'Down payment complete. Job can now proceed to production.');
                } else {
                    $remaining = $snapshot ? max(0, $snapshot['required_down_payment'] - $snapshot['total_paid']) : 0;
                    setFlash('success', 'Payment recorded. Remaining down payment: PHP ' . number_format($remaining, 2));
                }
            }
        }
    } else {
        setFlash('error', 'Invalid request action.');
    }

    header('Location: /smartprint-workflow/pages/client/jobs.php');
    exit;
}

$sql = "SELECT j.id, j.reference_number, j.title, st.name AS service_type, j.total_amount, j.quantity, js.code AS status, j.due_date,
               COALESCE(pay.total_paid, 0) AS total_paid,
               a.full_name AS admin_name,
               g.full_name AS artist_name
        FROM print_jobs j
        INNER JOIN service_types st ON st.id = j.service_type_id
        INNER JOIN job_statuses js ON js.id = j.status_id
        LEFT JOIN (
            SELECT job_id, SUM(amount) AS total_paid
            FROM job_payments
            GROUP BY job_id
        ) pay ON pay.job_id = j.id
        LEFT JOIN users a ON a.id = j.assigned_admin_id
        LEFT JOIN users g ON g.id = j.assigned_artist_id
        WHERE j.client_id = ?
        ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll();

$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'My Jobs';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="card">
    <h2>Submit New Print Request</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_job">
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
        <button class="btn" type="submit">Submit Request</button>
    </form>
</section>

<section class="card">
    <h2>My Requests</h2>
    <p>Down payment required before production starts: <strong>50%</strong> of total amount.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Title</th><th>Service</th><th>Qty</th><th>Amount</th><th>Down Payment</th><th>Status</th><th>Admin</th><th>Artist</th><th>Due Date</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $totalAmount = (float) $job['total_amount'];
                    $requiredDown = $totalAmount * 0.5;
                    $totalPaid = (float) $job['total_paid'];
                    $isDownPaymentMet = $totalPaid + 0.00001 >= $requiredDown;
                    ?>
                    <tr>
                        <td><?= e($job['reference_number']) ?></td>
                        <td><?= e($job['title']) ?></td>
                        <td><?= e($job['service_type']) ?></td>
                        <td><?= (int) $job['quantity'] ?></td>
                        <td>PHP <?= number_format($totalAmount, 2) ?></td>
                        <td>
                            <small>PHP <?= number_format($totalPaid, 2) ?> / <?= number_format($requiredDown, 2) ?></small>
                            <?php if (!$isDownPaymentMet): ?>
                                <form method="POST" class="form-grid" style="margin-top:8px;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="pay_down_payment">
                                    <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                    <input type="number" name="amount" min="1" step="0.01" placeholder="Pay amount" required>
                                    <input type="text" name="note" placeholder="Payment note (optional)">
                                    <button class="btn small" type="submit">Pay Down</button>
                                </form>
                            <?php else: ?>
                                <div class="status status-completed" style="margin-top:6px; display:inline-block;">50% Paid</div>
                            <?php endif; ?>
                        </td>
                        <td><span class="status status-<?= e($job['status']) ?>"><?= e(strtoupper($job['status'])) ?></span></td>
                        <td><?= e($job['admin_name'] ?? '-') ?></td>
                        <td><?= e($job['artist_name'] ?? '-') ?></td>
                        <td><?= e($job['due_date']) ?></td>
                        <td><a class="btn small" href="/smartprint-workflow/track.php?ref=<?= e($job['reference_number']) ?>" target="_blank">View QR</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
