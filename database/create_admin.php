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

if (strlen($password) > 72 || textLength($password) < 12) {
    putenv('ADMIN_PASSWORD');
    unset($_ENV['ADMIN_PASSWORD']);
    fwrite(STDERR, "The initial administrator password must contain at least 12 characters and no more than 72 bytes.\n");
    exit(1);
}

putenv('ADMIN_PASSWORD');
unset($_ENV['ADMIN_PASSWORD']);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
unset($password);
if ($passwordHash === false) {
    fwrite(STDERR, "Password hashing failed; no administrator was created.\n");
    exit(1);
}

fwrite(STDOUT, 'Administrator first name: ');
$firstName = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Administrator last name: ');
$lastName = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Administrator email: ');
$email = strtolower(trim((string) fgets(STDIN)));

if ($firstName === '' || strlen($firstName) > 100 || $lastName === '' || strlen($lastName) > 100) {
    fwrite(STDERR, "Enter a first and last name, each no longer than 100 bytes.\n");
    exit(1);
}
if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Enter a valid email address.\n");
    exit(1);
}

$pdo = database();
$existingAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($existingAdmins > 0) {
    fwrite(STDERR, "An administrator already exists. This command only creates the initial administrator.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $statement = $pdo->prepare(
        'INSERT INTO users (role, first_name, last_name, email, password_hash, status)
         VALUES (\'admin\', :first_name, :last_name, :email, :password_hash, \'active\')'
    );
    $statement->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);
    $pdo->commit();
} catch (\Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

fwrite(STDOUT, "Initial administrator created. Sign in through the bookstore login page.\n");
