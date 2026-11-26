<?php
declare(strict_types=1);

use function Bookstore\Config\database;
use function Bookstore\Support\textLength;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';

$password = getenv('ADMIN_PASSWORD');
if ($password === false) {
    fwrite(STDERR, "Set ADMIN_PASSWORD in the current terminal session using a hidden prompt, then run this script.\n");
    exit(1);
}

putenv('ADMIN_PASSWORD');
unset($_ENV['ADMIN_PASSWORD']);

if (strlen($password) > 72 || textLength($password) < 12) {
    unset($password);
    fwrite(STDERR, "The administrator password must contain at least 12 characters and no more than 72 bytes.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
unset($password);
if ($passwordHash === false) {
    fwrite(STDERR, "Password hashing failed; no account was changed.\n");
    exit(1);
}

fwrite(STDOUT, 'Administrator email: ');
$email = strtolower(trim((string) fgets(STDIN)));
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Enter a valid email address.\n");
    exit(1);
}

$pdo = database();
$lookup = $pdo->prepare("SELECT id FROM users WHERE email = :email AND role = 'admin' LIMIT 1");
$lookup->execute(['email' => $email]);
$adminId = $lookup->fetchColumn();
if ($adminId === false) {
    fwrite(STDERR, "No administrator account matches that email.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare(
        'UPDATE users
         SET password_hash = :password_hash, session_version = session_version + 1
         WHERE id = :admin_id'
    );
    $update->execute([
        'password_hash' => $passwordHash,
        'admin_id' => (int) $adminId,
    ]);
    $pdo->commit();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

fwrite(STDOUT, "Administrator password updated. Sign in with the new password.\n");
