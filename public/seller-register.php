<?php
declare(strict_types=1);

use Bookstore\Services\Authentication;
use function Bookstore\Config\bootstrap;
use function Bookstore\Config\database;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\limitText;
use function Bookstore\Support\normalizeGhanaPhone;
use function Bookstore\Support\textLength;
use function Bookstore\Support\verifyCsrfToken;

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
$csrfToken = csrfToken();
$errorMessage = '';
$successMessage = '';
$sellerTypes = [
    'individual' => 'Individual seller',
    'bookstore' => 'Bookstore',
    'publisher' => 'Publisher',
    'distributor' => 'Distributor',
    'educational_institution' => 'Educational institution',
    'other' => 'Other',
];
$fields = [
    'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    'store_name' => '', 'seller_type' => 'individual', 'description' => '',
    'region' => '', 'city' => '', 'business_address' => '',
    'business_registration_details' => '',
];

if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: ' . $basePath . '/', true, 303);
    exit;
}

if ($requestMethod === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'This form expired. Refresh the page and try again.';
    } else {
        foreach ($fields as $key => $_) {
            $value = $_POST[$key] ?? '';
            $fields[$key] = is_string($value) ? limitText(trim($value), $key === 'description' ? 2000 : 500) : '';
        }
        $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';
        $confirmation = is_string($_POST['password_confirmation'] ?? null) ? (string) $_POST['password_confirmation'] : '';
        $errors = [];

        foreach (['first_name', 'last_name', 'store_name', 'region', 'city', 'business_address'] as $required) {
            if ($fields[$required] === '') {
                $errors[] = 'Complete all required seller and contact fields.';
                break;
            }
        }
        if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        $normalizedPhone = normalizeGhanaPhone($fields['phone']);
        if ($normalizedPhone === null) {
            $errors[] = 'Enter a valid Ghanaian phone number.';
        }
        if (!array_key_exists($fields['seller_type'], $sellerTypes)) {
            $errors[] = 'Choose a valid seller type.';
        }
        if ($fields['description'] === '' || textLength($fields['description']) < 20) {
            $errors[] = 'Add a short business description of at least 20 characters.';
        }
        if (strlen($password) > 72 || textLength($password) < 12) {
            $errors[] = 'Choose a password with at least 12 characters and no more than 72 bytes.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'The passwords do not match.';
        }

        $logoPath = null;
        $logo = $_FILES['business_logo'] ?? null;
        if (is_array($logo) && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (($logo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($logo['size'] ?? 0) > 4 * 1024 * 1024) {
                $errors[] = 'The business logo must be an image no larger than 4 MB.';
            } elseif (!is_string($logo['tmp_name'] ?? null) || !is_uploaded_file($logo['tmp_name'])) {
                $errors[] = 'The uploaded logo could not be verified.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($logo['tmp_name']);
                $image = @getimagesize($logo['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!is_string($mime) || !isset($extensions[$mime]) || $image === false) {
                    $errors[] = 'Upload a valid JPG, PNG, or WebP business logo.';
                } else {
                    $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'sellers';
                    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                        $errors[] = 'The logo upload directory is unavailable.';
                    } else {
                        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                        $target = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
                        if (!move_uploaded_file($logo['tmp_name'], $target)) {
                            $errors[] = 'The business logo could not be saved.';
                        } else {
                            $logoPath = 'uploads/sellers/' . $filename;
                        }
                    }
                }
            }
        }

        try {
            $authentication = new Authentication(database());
            if ($authentication->isRateLimited($fields['email'], 'registration')) {
                http_response_code(429);
                $errorMessage = 'Too many account creation attempts. Please wait 15 minutes and try again.';
            } elseif ($errors !== []) {
                $authentication->recordAttempt($fields['email'], 'registration', false);
                $errorMessage = implode(' ', array_unique($errors));
            } else {
                try {
                    $authentication->registerSeller([
                        'first_name' => $fields['first_name'],
                        'last_name' => $fields['last_name'],
                        'email' => strtolower($fields['email']),
                        'phone' => $normalizedPhone,
                        'password' => $password,
                        'store_name' => $fields['store_name'],
                        'seller_type' => $fields['seller_type'],
                        'description' => $fields['description'],
                        'business_logo' => $logoPath,
                        'region' => $fields['region'],
                        'city' => $fields['city'],
                        'business_address' => $fields['business_address'],
                        'application_data' => [
                            'business_registration_details' => $fields['business_registration_details'],
                            'submitted_from' => 'seller_registration',
                        ],
                    ]);
                    $successMessage = 'Your seller application has been submitted. An administrator must approve it before you can list books.';
                    $fields = array_fill_keys(array_keys($fields), '');
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '23000') {
                        throw $exception;
                    }
                    $authentication->recordAttempt($fields['email'], 'registration', false);
                    $errorMessage = 'Unable to create the seller application with those details. Please use another email address.';
                }
            }
        } catch (PDOException $exception) {
            error_log('Bookstore seller registration database error: ' . $exception->getMessage());
            http_response_code(503);
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
            exit;
        }
    }
}

$pageTitle = 'Become a seller';
$searchQuery = '';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'seller-register.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
