<?php
declare(strict_types=1);

use Bookstore\Services\CustomerAccount;
use function Bookstore\Config\bootstrap;
use function Bookstore\Config\database;
use function Bookstore\Middleware\requireRole;
use function Bookstore\Support\consumeFlashMessage;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\limitText;
use function Bookstore\Support\normalizeGhanaPhone;
use function Bookstore\Support\rotateCsrfToken;
use function Bookstore\Support\setFlashMessage;
use function Bookstore\Support\textLength;
use function Bookstore\Support\verifyCsrfToken;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'validation.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'flash.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'authorization.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'CustomerAccount.php';

bootstrap();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$pageTitle = 'My account';
$searchQuery = '';
$errorMessage = '';
$csrfToken = csrfToken();

try {
    $pdo = database();
    $user = requireRole($pdo, 'customer', $basePath . '/login.php');
    $account = new CustomerAccount($pdo);

    if ($requestMethod === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errorMessage = 'This form expired. Refresh the page and try again.';
        } else {
            $action = $_POST['action'] ?? '';
            if ($action === 'profile') {
                $firstNameInput = $_POST['first_name'] ?? '';
                $lastNameInput = $_POST['last_name'] ?? '';
                $emailInput = $_POST['email'] ?? '';
                $phoneInput = $_POST['phone'] ?? '';
                $profile = [
                    'first_name' => is_string($firstNameInput) ? limitText(trim($firstNameInput), 100) : '',
                    'last_name' => is_string($lastNameInput) ? limitText(trim($lastNameInput), 100) : '',
                    'email' => is_string($emailInput) ? strtolower(limitText(trim($emailInput), 254)) : '',
                    'phone' => is_string($phoneInput) ? limitText(trim($phoneInput), 24) : '',
                ];
                $errors = [];
                if (!is_string($firstNameInput) || strlen($firstNameInput) > 100 || $profile['first_name'] === '') {
                    $errors[] = 'Enter a first name of no more than 100 bytes.';
                }
                if (!is_string($lastNameInput) || strlen($lastNameInput) > 100 || $profile['last_name'] === '') {
                    $errors[] = 'Enter a last name of no more than 100 bytes.';
                }
                if (!is_string($emailInput) || strlen($emailInput) > 254 || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Enter a valid email address.';
                }

                $phone = null;
                if ($profile['phone'] !== '') {
                    $phone = is_string($phoneInput) && strlen($phoneInput) <= 24
                        ? normalizeGhanaPhone($profile['phone'])
                        : null;
                    if ($phone === null) {
                        $errors[] = 'Enter a valid Ghanaian phone number, such as 024 123 4567.';
                    }
                } elseif ($phoneInput !== '') {
                    $errors[] = 'Enter a valid Ghanaian phone number, such as 024 123 4567.';
                }

                if ($errors !== []) {
                    $errorMessage = implode(' ', $errors);
                } else {
                    try {
                        $account->updateProfile(
                            (int) $user['id'],
                            $profile['first_name'],
                            $profile['last_name'],
                            $profile['email'],
                            $phone
                        );
                        $_SESSION['user']['first_name'] = $profile['first_name'];
                        $_SESSION['user']['last_name'] = $profile['last_name'];
                        $_SESSION['user']['email'] = $profile['email'];
                        setFlashMessage('success', 'Your profile has been updated.');
                        header('Location: ' . $basePath . '/account.php', true, 303);
                        exit;
                    } catch (PDOException $exception) {
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }
                        $errorMessage = 'That email address is already associated with an account.';
                    }
                }
                $user = array_merge($user, $profile);
            } elseif ($action === 'password') {
                $currentPasswordInput = $_POST['current_password'] ?? '';
                $newPasswordInput = $_POST['new_password'] ?? '';
                $confirmationInput = $_POST['password_confirmation'] ?? '';
                $currentPassword = is_string($currentPasswordInput) ? $currentPasswordInput : '';
                $newPassword = is_string($newPasswordInput) ? $newPasswordInput : '';
                $confirmation = is_string($confirmationInput) ? $confirmationInput : '';
                if ($currentPassword === '') {
                    $errorMessage = 'Enter your current password.';
                } elseif (strlen($newPassword) > 72 || textLength($newPassword) < 12) {
                    $errorMessage = 'Choose a new password with at least 12 characters and no more than 72 bytes.';
                } elseif ($newPassword !== $confirmation) {
                    $errorMessage = 'The new passwords do not match.';
                } else {
                    try {
                        $newVersion = $account->changePassword((int) $user['id'], $currentPassword, $newPassword);
                        if (!session_regenerate_id(true)) {
                            error_log('Bookstore could not rotate the session after a password change.');
                            if (!\Bookstore\Support\destroyCurrentSession()) {
                                error_log('Bookstore could not fully destroy a session after password change.');
                            }
                            http_response_code(500);
                            exit('Your password was changed. Please sign in again to continue.');
                        } else {
                            $_SESSION['user']['session_version'] = $newVersion;
                            rotateCsrfToken();
                            setFlashMessage('success', 'Your password has been changed. Other active sessions were signed out.');
                            header('Location: ' . $basePath . '/account.php', true, 303);
                            exit;
                        }
                    } catch (DomainException $exception) {
                        $errorMessage = $exception->getMessage();
                    }
                }
            } else {
                http_response_code(400);
                $errorMessage = 'Choose a valid account action.';
            }
        }
    }

    $summary = $account->dashboardSummary((int) $user['id']);
    $recentOrders = $account->orders((int) $user['id'], 5, 0);
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore customer account database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
} catch (RuntimeException $exception) {
    error_log('Bookstore customer account error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'account.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
