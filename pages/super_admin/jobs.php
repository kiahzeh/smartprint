<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['super_admin']);

$sql = "SELECT j.id, j.reference_number, j.title, st.name AS service_type, j.quantity, js.code AS status, j.due_date,
               c.full_name AS client_name,
               a.full_name AS admin_name,
               g.full_name AS artist_name
        FROM print_jobs j
        INNER JOIN service_types st ON st.id = j.service_type_id
        INNER JOIN job_statuses js ON js.id = j.status_id
        LEFT JOIN users c ON c.id = j.client_id
        LEFT JOIN users a ON a.id = j.assigned_admin_id
        LEFT JOIN users g ON g.id = j.assigned_artist_id
        ORDER BY j.created_at DESC";
$jobs = $pdo->query($sql)->fetchAll();

$pageTitle = 'All Jobs';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="card">
    <h2>All Print Jobs</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Reference</th><th>Title</th><th>Client</th><th>Service</th><th>Qty</th><th>Status</th><th>Admin</th><th>Artist</th><th>Due Date</th></tr>
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
                        <td><?= e($job['admin_name'] ?? '-') ?></td>
                        <td><?= e($job['artist_name'] ?? '-') ?></td>
                        <td><?= e($job['due_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
