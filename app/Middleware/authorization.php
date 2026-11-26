<?php
declare(strict_types=1);

namespace Bookstore\Middleware;

use PDO;
use function Bookstore\Support\destroyCurrentSession;

function currentUser(): ?array
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !isset($user['id'], $user['role'])) {
        return null;
    }

    return $user;
}

function hasRole(string $role): bool
{
    return (currentUser()['role'] ?? null) === $role;
}

function requireAuthenticatedUser(PDO $pdo, string $loginUrl): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    $statement = $pdo->prepare(
        'SELECT role, status, session_version FROM users WHERE id = :user_id LIMIT 1'
    );
    $statement->execute(['user_id' => (int) $user['id']]);
    $currentAccount = $statement->fetch();
    if (
        $currentAccount === false
        || $currentAccount['status'] !== 'active'
        || $currentAccount['role'] !== $user['role']
        || (int) $currentAccount['session_version'] !== (int) ($user['session_version'] ?? -1)
    ) {
        if (!destroyCurrentSession()) {
            error_log('Bookstore could not fully destroy an invalidated session.');
        }
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    return $user;
}

function requireRole(PDO $pdo, string $role, string $loginUrl): array
{
    $user = requireAuthenticatedUser($pdo, $loginUrl);
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You are not authorized to view this page.');
    }

    return $user;
}
