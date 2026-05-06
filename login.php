<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /smartprint-workflow/signin.php');
    exit;
}

requireCsrf('/smartprint-workflow/signin.php');

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    setFlash('error', 'Email and password are required.');
    header('Location: /smartprint-workflow/signin.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.password_hash, u.is_active, r.code AS role
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
    setFlash('error', 'Invalid login credentials.');
    header('Location: /smartprint-workflow/signin.php');
    exit;
}

$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'role' => $user['role'],
];

header('Location: /smartprint-workflow/dashboard.php');
exit;
