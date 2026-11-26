<?php
declare(strict_types=1);

use Bookstore\Services\Authentication;
use Bookstore\Services\CustomerCart;
use function Bookstore\Config\database;
use function Bookstore\Config\bootstrap;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\limitText;
use function Bookstore\Support\verifyCsrfToken;
use function Bookstore\Support\rotateCsrfToken;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Authentication.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'CustomerCart.php';

bootstrap();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');

if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $basePath . '/', true, 303);
    exit;
}

$email = '';
$errorMessage = '';
$csrfToken = csrfToken();

if ($requestMethod === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'This form expired. Refresh the page and try again.';
    } else {
        $rawEmail = $_POST['email'] ?? '';
        $email = is_string($rawEmail) ? strtolower(limitText(trim($rawEmail), 254)) : '';
        $rawPassword = $_POST['password'] ?? '';
        $password = is_string($rawPassword) ? $rawPassword : '';

        try {
            $pdo = database();
            $authentication = new Authentication($pdo);
            if ($authentication->isRateLimited($email, 'login')) {
                http_response_code(429);
                $errorMessage = 'Too many sign-in attempts. Please wait 15 minutes before trying again.';
            } elseif (!is_string($rawEmail) || strlen($rawEmail) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 1024) {
                $authentication->recordAttempt($email, 'login', false);
                $errorMessage = 'Email or password is incorrect.';
            } else {
                $user = $authentication->authenticate($email, $password);
                $authentication->recordAttempt($email, 'login', $user !== null);
                if ($user === null) {
                    $errorMessage = 'Email or password is incorrect.';
                } elseif (!session_regenerate_id(true)) {
                    error_log('Bookstore could not regenerate a session ID after login.');
                    http_response_code(500);
                    $errorMessage = 'We could not securely sign you in. Please try again.';
                } else {
                    $_SESSION['user'] = $user;
                    $_SESSION['cart_item_count'] = $user['role'] === 'customer'
                        ? (new CustomerCart($pdo))->itemCount((int) $user['id'])
                        : 0;
                    rotateCsrfToken();
                    header('Location: ' . $basePath . '/', true, 303);
                    exit;
                }
            }
        } catch (\PDOException $exception) {
            error_log('Bookstore authentication database error: ' . $exception->getMessage());
            http_response_code(503);
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
            exit;
        }
    }
}

$pageTitle = 'Sign in';
$searchQuery = '';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'login.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
