<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $message;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!currentUser()) {
        setFlash('error', 'Please login first.');
        header('Location: /smartprint-workflow/signin.php');
        exit;
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    $user = currentUser();

    if (!$user || !in_array($user['role'], $roles, true)) {
        setFlash('error', 'You do not have access to that page.');
        header('Location: /smartprint-workflow/dashboard.php');
        exit;
    }
}

function generateReferenceNumber(PDO $pdo): string
{
    $year = date('Y');
    $refStmt = $pdo->prepare('SELECT reference_number FROM print_jobs WHERE reference_number LIKE ?');
    $refStmt->execute(["REF-{$year}-%"]);
    $references = $refStmt->fetchAll(PDO::FETCH_COLUMN);

    $lastNum = 0;
    foreach ($references as $reference) {
        if (!is_string($reference)) {
            continue;
        }

        if (preg_match('/^REF-\d{4}-(\d+)$/', $reference, $matches) === 1) {
            $lastNum = max($lastNum, (int) $matches[1]);
        }
    }

    $nextNum = $lastNum + 1;
    return 'REF-' . $year . '-' . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
}

function fetchJobPaymentSnapshot(PDO $pdo, int $jobId): ?array
{
    $stmt = $pdo->prepare("
        SELECT j.id,
               j.total_amount,
               COALESCE(SUM(p.amount), 0) AS total_paid
        FROM print_jobs j
        LEFT JOIN job_payments p ON p.job_id = j.id
        WHERE j.id = ?
        GROUP BY j.id, j.total_amount
    ");
    $stmt->execute([$jobId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $totalAmount = (float) $row['total_amount'];
    $totalPaid = (float) $row['total_paid'];
    $requiredDown = $totalAmount * 0.5;

    return [
        'total_amount' => $totalAmount,
        'total_paid' => $totalPaid,
        'required_down_payment' => $requiredDown,
        'is_met' => $totalPaid + 0.00001 >= $requiredDown,
    ];
}

function csrfToken(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(string $token): bool
{
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function requireCsrf(string $redirectPath): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!is_string($token) || !verifyCsrfToken($token)) {
        setFlash('error', 'Invalid form token. Please try again.');
        header('Location: ' . $redirectPath);
        exit;
    }
}
