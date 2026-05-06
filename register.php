<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

requireCsrf('/smartprint-workflow/signup.php');

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
    setFlash('error', 'Please fill all required fields.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash('error', 'Please enter a valid email address.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

if (strlen($password) < 8) {
    setFlash('error', 'Password must be at least 8 characters.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

if ($password !== $confirmPassword) {
    setFlash('error', 'Password confirmation does not match.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

$clientRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'client' LIMIT 1");
$clientRoleStmt->execute();
$clientRoleId = (int) $clientRoleStmt->fetchColumn();

if ($clientRoleId <= 0) {
    setFlash('error', 'Client role is not configured.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

$existingStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$existingStmt->execute([$email]);
if ($existingStmt->fetch()) {
    setFlash('error', 'Email is already registered.');
    header('Location: /smartprint-workflow/signup.php');
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$insertStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)');
$insertStmt->execute([$fullName, $email, $hash, $clientRoleId]);

setFlash('success', 'Account created successfully. You can now log in.');
header('Location: /smartprint-workflow/signin.php');
exit;
