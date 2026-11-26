<?php
declare(strict_types=1);

namespace Bookstore\Config;

use RuntimeException;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (ini_set('session.use_strict_mode', '1') === false
        || ini_set('session.use_only_cookies', '1') === false
        || ini_set('session.use_trans_sid', '0') === false) {
        throw new RuntimeException('Unable to apply secure session settings.');
    }
    session_name('BOOKSTORESESSID');

    $https = isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    if (!session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ])) {
        throw new RuntimeException('Unable to configure secure session cookies.');
    }

    if (!session_start()) {
        throw new RuntimeException('Unable to start a secure session.');
    }
}
