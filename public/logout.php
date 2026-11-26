<?php
declare(strict_types=1);

use function Bookstore\Config\bootstrap;
use function Bookstore\Support\verifyCsrfToken;
use function Bookstore\Support\destroyCurrentSession;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
bootstrap();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid request token.');
}

if (!destroyCurrentSession()) {
    error_log('Bookstore could not fully destroy the logged-out session.');
    http_response_code(500);
    exit('Unable to complete sign out. Please close the browser and try again.');
}

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
header('Location: ' . $basePath . '/', true, 303);
