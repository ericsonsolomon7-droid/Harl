<?php
declare(strict_types=1);

namespace Bookstore\Config;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'security.php';

function bootstrap(): void
{
    \Bookstore\Support\sendSecurityHeaders();
    startSecureSession();
}
