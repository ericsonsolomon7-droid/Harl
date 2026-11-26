<?php
declare(strict_types=1);

use Bookstore\Services\Authentication;
use function Bookstore\Config\database;
use function Bookstore\Config\bootstrap;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\limitText;
use function Bookstore\Support\rotateCsrfToken;
use function Bookstore\Support\textLength;
use function Bookstore\Support\verifyCsrfToken;
use function Bookstore\Support\normalizeGhanaPhone;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'validation.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Authentication.php';

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

$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$errorMessage = '';
$csrfToken = csrfToken();

if ($requestMethod === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'This form expired. Refresh the page and try again.';
    } else {
        $firstNameInput = $_POST['first_name'] ?? '';
        $lastNameInput = $_POST['last_name'] ?? '';
        $emailInput = $_POST['email'] ?? '';
        $phoneInput = $_POST['phone'] ?? '';
        $passwordInput = $_POST['password'] ?? '';
        $confirmationInput = $_POST['password_confirmation'] ?? '';

        $firstName = is_string($firstNameInput) ? limitText(trim($firstNameInput), 100) : '';
        $lastName = is_string($lastNameInput) ? limitText(trim($lastNameInput), 100) : '';
        $email = is_string($emailInput) ? strtolower(limitText(trim($emailInput), 254)) : '';
        $phone = is_string($phoneInput) ? limitText(trim($phoneInput), 24) : '';
        $password = is_string($passwordInput) ? $passwordInput : '';
        $confirmation = is_string($confirmationInput) ? $confirmationInput : '';
        $errors = [];

        if (!is_string($firstNameInput) || strlen($firstNameInput) > 100 || !is_string($lastNameInput) || strlen($lastNameInput) > 100) {
            $errors[] = 'Names must be no longer than 100 bytes.';
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'Enter your first and last name.';
        }
        if (!is_string($emailInput) || strlen($emailInput) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        $normalizedPhone = null;
        if ($phone !== '') {
            $normalizedPhone = is_string($phoneInput) && strlen($phoneInput) <= 24 ? normalizeGhanaPhone($phone) : null;
            if ($normalizedPhone === null) {
                $errors[] = 'Enter a valid Ghanaian phone number, such as 024 123 4567.';
            }
        } elseif ($phoneInput !== '') {
            $errors[] = 'Enter a valid Ghanaian phone number, such as 024 123 4567.';
        }
        if (strlen($password) > 72) {
            $errors[] = 'Passwords may not exceed 72 bytes.';
        } elseif (textLength($password) < 12) {
            $errors[] = 'Choose a password with at least 12 characters and no more than 72 bytes.';
        }
        if (strlen($confirmation) > 72) {
            $errors[] = 'Password confirmation may not exceed 72 bytes.';
        } elseif (strlen($password) <= 72 && $password !== $confirmation) {
            $errors[] = 'The passwords do not match.';
        }

        try {
            $authentication = new Authentication(database());
            if ($authentication->isRateLimited($email, 'registration')) {
                http_response_code(429);
                $errorMessage = 'Too many account creation attempts. Please wait 15 minutes and try again.';
            } elseif ($errors !== []) {
                $authentication->recordAttempt($email, 'registration', false);
                $errorMessage = implode(' ', $errors);
            } else {
                try {
                    $user = $authentication->register($firstName, $lastName, $email, $normalizedPhone, $password);
                    if (!session_regenerate_id(true)) {
                        error_log('Bookstore could not regenerate a session ID after registration.');
                        http_response_code(500);
                        $errorMessage = 'Your account was created, but we could not securely sign you in. Please sign in to continue.';
                    } else {
                        $_SESSION['user'] = $user;
                        $_SESSION['cart_item_count'] = 0;
                        rotateCsrfToken();
                        header('Location: ' . $basePath . '/', true, 303);
                        exit;
                    }
                } catch (\PDOException $exception) {
                    if ($exception->getCode() !== '23000') {
                        throw $exception;
                    }

                    $authentication->recordAttempt($email, 'registration', false);
                    $errorMessage = 'Unable to create the account with those details. Please try signing in or use another email.';
                }
            }
        } catch (\PDOException $exception) {
            error_log('Bookstore registration database error: ' . $exception->getMessage());
            http_response_code(503);
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
            exit;
        }
    }
}

$pageTitle = 'Create an account';
$searchQuery = '';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'register.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
