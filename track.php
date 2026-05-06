<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$job = null;
$error = null;
$referenceNumber = trim($_GET['ref'] ?? $_POST['reference'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/track.php');
}

if ($referenceNumber !== '') {
    $stmt = $pdo->prepare("
        SELECT j.id, j.reference_number, j.title, j.description, j.quantity, j.due_date, j.created_at, j.updated_at,
               j.total_amount,
               st.name AS service_type,
               js.code AS status,
               COALESCE(pay.total_paid, 0) AS total_paid,
               u.full_name AS client_name
        FROM print_jobs j
        INNER JOIN service_types st ON st.id = j.service_type_id
        INNER JOIN job_statuses js ON js.id = j.status_id
        LEFT JOIN (
            SELECT job_id, SUM(amount) AS total_paid
            FROM job_payments
            GROUP BY job_id
        ) pay ON pay.job_id = j.id
        LEFT JOIN users u ON u.id = j.client_id
        WHERE j.reference_number = ?
    ");
    $stmt->execute([$referenceNumber]);
    $job = $stmt->fetch();

    if (!$job) {
        $error = 'Reference number not found.';
    }
}

$pageTitle = 'Track Job';
require_once __DIR__ . '/includes/header.php';
?>

<section class="card auth-card">
    <h2>Track Your Print Job</h2>
    <p>Enter your reference number or scan the QR code below.</p>

    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <label>Reference Number
            <input type="text" name="reference" placeholder="e.g., REF-2026-0001" value="<?= e($referenceNumber) ?>" required>
        </label>
        <button type="submit" class="btn">Search</button>
    </form>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($job): ?>
        <section class="card">
            <h3><?= e($job['title']) ?></h3>
            <p><strong>Reference:</strong> <?= e($job['reference_number']) ?></p>
            <p><strong>Service:</strong> <?= e($job['service_type']) ?></p>
            <p><strong>Quantity:</strong> <?= (int) $job['quantity'] ?></p>
            <p><strong>Total Amount:</strong> PHP <?= number_format((float) $job['total_amount'], 2) ?></p>
            <?php
            $requiredDown = ((float) $job['total_amount']) * 0.5;
            $totalPaid = (float) $job['total_paid'];
            ?>
            <p><strong>Down Payment:</strong> PHP <?= number_format($totalPaid, 2) ?> / <?= number_format($requiredDown, 2) ?></p>
            <p><strong>Due Date:</strong> <?= e($job['due_date']) ?></p>
            <p><strong>Status:</strong> <span class="status status-<?= e($job['status']) ?>"><?= e(strtoupper($job['status'])) ?></span></p>
            
            <?php if ($job['description']): ?>
                <p><strong>Description:</strong> <?= e($job['description']) ?></p>
            <?php endif; ?>

            <div style="margin-top: 20px; text-align: center;">
                <h4>Scan or Save QR Code</h4>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode('https://localhost/smartprint-workflow/track.php?ref=' . $job['reference_number']) ?>" alt="QR Code" style="border: 2px solid var(--border); border-radius: 8px; padding: 8px;">
            </div>
        </section>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
