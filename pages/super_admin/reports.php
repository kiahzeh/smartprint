<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['super_admin']);

$reportType = $_GET['type'] ?? 'weekly';
$endDate = new DateTime();
$startDate = new DateTime();

if ($reportType === 'monthly') {
    $startDate->modify('first day of this month');
    $period = 'Monthly Report - ' . $endDate->format('F Y');
} else {
    $startDate->modify('-6 days');
    $period = 'Weekly Report - ' . $startDate->format('M d') . ' to ' . $endDate->format('M d, Y');
}

$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

// Total jobs in period
$totalJobsStmt = $pdo->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN js.code = 'completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN js.code = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
           SUM(CASE WHEN js.code IN ('pending', 'for_layout', 'for_approval', 'for_print') THEN 1 ELSE 0 END) as inprogress
    FROM print_jobs pj
    INNER JOIN job_statuses js ON js.id = pj.status_id
    WHERE DATE(pj.created_at) BETWEEN ? AND ?
");
$totalJobsStmt->execute([$startStr, $endStr]);
$jobStats = $totalJobsStmt->fetch();

// Jobs by status
$statusStmt = $pdo->prepare("
    SELECT js.code AS status, COUNT(*) as count
    FROM print_jobs pj
    INNER JOIN job_statuses js ON js.id = pj.status_id
    WHERE DATE(pj.created_at) BETWEEN ? AND ?
    GROUP BY js.code
    ORDER BY count DESC
");
$statusStmt->execute([$startStr, $endStr]);
$statusBreakdown = $statusStmt->fetchAll();

// Top service types
$serviceStmt = $pdo->prepare("
    SELECT st.name AS service_type, COUNT(*) as count, SUM(pj.quantity) as total_qty
    FROM print_jobs pj
    INNER JOIN service_types st ON st.id = pj.service_type_id
    WHERE DATE(pj.created_at) BETWEEN ? AND ?
    GROUP BY st.name
    ORDER BY count DESC
    LIMIT 10
");
$serviceStmt->execute([$startStr, $endStr]);
$serviceTypes = $serviceStmt->fetchAll();

// Artist performance
$artistStmt = $pdo->prepare("
    SELECT u.full_name, COUNT(pj.id) as jobs_assigned,
           SUM(CASE WHEN js.code = 'completed' THEN 1 ELSE 0 END) as completed
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN print_jobs pj ON u.id = pj.assigned_artist_id 
           AND DATE(pj.created_at) BETWEEN ? AND ?
    LEFT JOIN job_statuses js ON js.id = pj.status_id
    WHERE r.code = 'artist'
    GROUP BY u.id
    ORDER BY jobs_assigned DESC
");
$artistStmt->execute([$startStr, $endStr]);
$artistPerformance = $artistStmt->fetchAll();

// Admin activity
$adminStmt = $pdo->prepare("
    SELECT u.full_name, COUNT(pj.id) as jobs_managed
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    LEFT JOIN print_jobs pj ON u.id = pj.assigned_admin_id 
           AND DATE(pj.created_at) BETWEEN ? AND ?
    WHERE r.code = 'admin'
    GROUP BY u.id
    ORDER BY jobs_managed DESC
");
$adminStmt->execute([$startStr, $endStr]);
$adminActivity = $adminStmt->fetchAll();

// New clients
$clientStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT client_id) as new_client_jobs
    FROM print_jobs
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$clientStmt->execute([$startStr, $endStr]);
$clientData = $clientStmt->fetch();

$pageTitle = 'Reports';
require_once __DIR__ . '/../../includes/header.php';
?>

<section class="card">
    <h2><?= e($period) ?></h2>
    <div style="margin-bottom: 18px; display: flex; gap: 12px;">
        <a class="btn <?= $reportType === 'weekly' ? '' : 'btn-outline' ?>" href="?type=weekly">Weekly</a>
        <a class="btn <?= $reportType === 'monthly' ? '' : 'btn-outline' ?>" href="?type=monthly">Monthly</a>
    </div>
</section>

<!-- Summary Stats -->
<section class="grid-3">
    <article class="card stat">
        <h3>Total Jobs</h3>
        <p><?= (int) $jobStats['total'] ?></p>
    </article>
    <article class="card stat">
        <h3>Completed</h3>
        <p><?= (int) $jobStats['completed'] ?></p>
    </article>
    <article class="card stat">
        <h3>In Progress</h3>
        <p><?= (int) $jobStats['inprogress'] ?></p>
    </article>
</section>

<!-- Status Breakdown -->
<section class="card">
    <h2>Jobs by Status</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Status</th><th>Count</th></tr>
            </thead>
            <tbody>
                <?php foreach ($statusBreakdown as $s): ?>
                    <tr>
                        <td><span class="status status-<?= e($s['status']) ?>"><?= e(strtoupper($s['status'])) ?></span></td>
                        <td><?= (int) $s['count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Service Types -->
<section class="card">
    <h2>Top Service Types</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Service</th><th>Orders</th><th>Total Qty</th></tr>
            </thead>
            <tbody>
                <?php foreach ($serviceTypes as $svc): ?>
                    <tr>
                        <td><?= e($svc['service_type']) ?></td>
                        <td><?= (int) $svc['count'] ?></td>
                        <td><?= (int) $svc['total_qty'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Artist Performance -->
<section class="card">
    <h2>Artist Performance</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Artist</th><th>Jobs Assigned</th><th>Completed</th></tr>
            </thead>
            <tbody>
                <?php foreach ($artistPerformance as $a): ?>
                    <tr>
                        <td><?= e($a['full_name'] ?? 'Unassigned') ?></td>
                        <td><?= (int) $a['jobs_assigned'] ?></td>
                        <td><?= (int) $a['completed'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Admin Activity -->
<section class="card">
    <h2>Admin Activity</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Admin</th><th>Jobs Managed</th></tr>
            </thead>
            <tbody>
                <?php foreach ($adminActivity as $adm): ?>
                    <tr>
                        <td><?= e($adm['full_name'] ?? 'Unassigned') ?></td>
                        <td><?= (int) $adm['jobs_managed'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Client Activity -->
<section class="card">
    <h2>Activity Summary</h2>
    <p><strong>Client Orders:</strong> <?= (int) $clientData['new_client_jobs'] ?></p>
    <p><strong>Cancellation Rate:</strong> <?= $jobStats['total'] > 0 ? round(($jobStats['cancelled'] / $jobStats['total']) * 100, 1) : 0 ?>%</p>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
