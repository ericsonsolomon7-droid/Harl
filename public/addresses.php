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
use function Bookstore\Support\setFlashMessage;
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
$pageTitle = 'Delivery addresses';
$searchQuery = '';
$errorMessage = '';
$formData = [
    'recipient_name' => '',
    'phone' => '',
    'region' => '',
    'city' => '',
    'address_line' => '',
    'landmark' => '',
    'delivery_instructions' => '',
    'is_default' => 0,
];
$editAddressId = null;
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
            $actionInput = $_POST['action'] ?? '';
            $addressIdInput = $_POST['address_id'] ?? '';
            $addressId = is_string($addressIdInput) && ctype_digit($addressIdInput) && (int) $addressIdInput > 0
                ? (int) $addressIdInput
                : null;

            if ($actionInput === 'delete' || $actionInput === 'default') {
                if ($addressId === null) {
                    http_response_code(400);
                    $errorMessage = 'Choose a valid delivery address.';
                } else {
                    try {
                        if ($actionInput === 'delete') {
                            $account->deleteAddress((int) $user['id'], $addressId);
                            setFlashMessage('success', 'The delivery address has been removed.');
                        } else {
                            $account->setDefaultAddress((int) $user['id'], $addressId);
                            setFlashMessage('success', 'Your default delivery address has been updated.');
                        }
                        header('Location: ' . $basePath . '/addresses.php', true, 303);
                        exit;
                    } catch (DomainException $exception) {
                        $errorMessage = $exception->getMessage();
                    }
                }
            } elseif ($actionInput === 'save' && array_key_exists('address_id', $_POST) && $addressId === null) {
                http_response_code(400);
                $errorMessage = 'Choose a valid delivery address.';
            } elseif ($actionInput === 'save') {
                $editAddressId = $addressId;
                $rawAddress = [
                    'recipient_name' => $_POST['recipient_name'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'region' => $_POST['region'] ?? '',
                    'city' => $_POST['city'] ?? '',
                    'address_line' => $_POST['address_line'] ?? '',
                    'landmark' => $_POST['landmark'] ?? '',
                    'delivery_instructions' => $_POST['delivery_instructions'] ?? '',
                ];
                foreach ($rawAddress as $key => $value) {
                    $formData[$key] = is_string($value) ? trim($value) : '';
                }
                $formData['recipient_name'] = limitText($formData['recipient_name'], 200);
                $formData['phone'] = limitText($formData['phone'], 30);
                $formData['region'] = limitText($formData['region'], 100);
                $formData['city'] = limitText($formData['city'], 120);
                $formData['address_line'] = limitText($formData['address_line'], 255);
                $formData['landmark'] = limitText($formData['landmark'], 255);
                $formData['delivery_instructions'] = limitText($formData['delivery_instructions'], 1000);
                $formData['is_default'] = ($_POST['is_default'] ?? '') === '1' ? 1 : 0;
                $errors = [];

                $limits = [
                    'recipient_name' => 200,
                    'phone' => 30,
                    'region' => 100,
                    'city' => 120,
                    'address_line' => 255,
                    'landmark' => 255,
                    'delivery_instructions' => 1000,
                ];
                foreach ($limits as $key => $maxBytes) {
                    if (!is_string($rawAddress[$key]) || strlen($rawAddress[$key]) > $maxBytes) {
                        $errors[] = 'One or more address fields exceed the allowed length.';
                        break;
                    }
                }
                foreach (['recipient_name', 'region', 'city', 'address_line'] as $requiredField) {
                    if ($formData[$requiredField] === '') {
                        $errors[] = 'Recipient, region, city, and street address are required.';
                        break;
                    }
                }
                $normalizedPhone = normalizeGhanaPhone($formData['phone']);
                if ($normalizedPhone === null) {
                    $errors[] = 'Enter a valid Ghanaian phone number, such as 024 123 4567.';
                }

                if ($errors !== []) {
                    $errorMessage = implode(' ', array_unique($errors));
                } else {
                    $formData['phone'] = $normalizedPhone;
                    $addressData = $formData;
                    $addressData['landmark'] = $addressData['landmark'] === '' ? null : $addressData['landmark'];
                    $addressData['delivery_instructions'] = $addressData['delivery_instructions'] === '' ? null : $addressData['delivery_instructions'];
                    try {
                        $account->saveAddress(
                            (int) $user['id'],
                            $editAddressId,
                            $addressData,
                            $formData['is_default'] === 1
                        );
                        setFlashMessage('success', 'Your delivery address has been saved.');
                        header('Location: ' . $basePath . '/addresses.php', true, 303);
                        exit;
                    } catch (DomainException $exception) {
                        $errorMessage = $exception->getMessage();
                    }
                }
            } else {
                http_response_code(400);
                $errorMessage = 'Choose a valid address action.';
            }
        }
    } elseif (isset($_GET['edit'])) {
        $editInput = $_GET['edit'];
        if (is_string($editInput) && ctype_digit($editInput) && (int) $editInput > 0) {
            $editAddressId = (int) $editInput;
            $storedAddress = $account->address((int) $user['id'], $editAddressId);
            if ($storedAddress === null) {
                http_response_code(404);
                $editAddressId = null;
                $errorMessage = 'That delivery address could not be found.';
            } else {
                $formData = array_merge($formData, $storedAddress);
            }
        } else {
            http_response_code(400);
            $errorMessage = 'Choose a valid delivery address.';
        }
    }

    $addresses = $account->addresses((int) $user['id']);
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore customer address database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
} catch (RuntimeException $exception) {
    error_log('Bookstore customer address error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'addresses.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
