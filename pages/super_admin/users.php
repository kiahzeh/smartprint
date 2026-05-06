<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
requireRole(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf('/smartprint-workflow/pages/super_admin/users.php');

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    $roleMapStmt = $pdo->query('SELECT id, code FROM roles');
    $roleMap = [];
    foreach ($roleMapStmt->fetchAll() as $roleRow) {
        $roleMap[$roleRow['code']] = (int) $roleRow['id'];
    }

    if ($fullName === '' || $email === '' || $password === '' || !isset($roleMap[$role])) {
        setFlash('error', 'Please fill all required fields.');
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);

        if ($check->fetch()) {
            setFlash('error', 'Email already exists.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)');
            $insert->execute([$fullName, $email, $hash, $roleMap[$role]]);
            setFlash('success', 'User account created successfully.');
        }
    }

    header('Location: /smartprint-workflow/pages/super_admin/users.php');
    exit;
}

$roles = $pdo->query('SELECT code, label FROM roles ORDER BY id ASC')->fetchAll();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, r.code AS role, u.is_active, u.created_at
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    ORDER BY u.id DESC
")->fetchAll();
$error = getFlash('error');
$success = getFlash('success');
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../../includes/header.php';
?>
<section class="card">
    <h2>Create User</h2>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

    <form method="POST" class="form-grid">
        <?= csrfField() ?>
        <label>Full Name
            <input type="text" name="full_name" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <label>Role
            <select name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['code']) ?>"><?= e($r['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit">Create User</button>
    </form>
</section>

<section class="card">
    <h2>Users List</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int) $u['id'] ?></td>
                        <td><?= e($u['full_name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['role']) ?></td>
                        <td><?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                        <td><?= e($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
