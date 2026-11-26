<?php
declare(strict_types=1);

use Bookstore\Services\CustomerCart;
use function Bookstore\Config\bootstrap;
use function Bookstore\Config\database;
use function Bookstore\Middleware\requireRole;
use function Bookstore\Support\consumeFlashMessage;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\setFlashMessage;
use function Bookstore\Support\verifyCsrfToken;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'flash.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'authorization.php';
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
$pageTitle = 'Shopping cart';
$searchQuery = '';
$errorMessage = '';
$csrfToken = csrfToken();

try {
    $pdo = database();
    $user = requireRole($pdo, 'customer', $basePath . '/login.php');
    $cart = new CustomerCart($pdo);
    if ($requestMethod === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errorMessage = 'This form expired. Refresh the page and try again.';
        } else {
            $action = $_POST['action'] ?? '';
            $bookIdInput = $_POST['book_id'] ?? '';
            $bookId = is_string($bookIdInput) && ctype_digit($bookIdInput) && (int) $bookIdInput > 0
                ? (int) $bookIdInput
                : null;
            if ($bookId === null || !in_array($action, ['add', 'update', 'remove'], true)) {
                http_response_code(400);
                $errorMessage = 'Choose a valid cart action.';
            } else {
                try {
                    if ($action === 'remove') {
                        $cart->removeItem((int) $user['id'], $bookId);
                        setFlashMessage('success', 'The book was removed from your cart.');
                    } else {
                        $quantityInput = $_POST['quantity'] ?? '';
                        $quantity = is_string($quantityInput) && ctype_digit($quantityInput)
                            ? (int) $quantityInput
                            : 0;
                        if ($action === 'add') {
                            $cart->addItem((int) $user['id'], $bookId, $quantity);
                            setFlashMessage('success', 'The book was added to your cart.');
                        } else {
                            $cart->updateItem((int) $user['id'], $bookId, $quantity);
                            setFlashMessage('success', 'Your cart quantity was updated.');
                        }
                    }
                    $_SESSION['cart_item_count'] = $cart->itemCount((int) $user['id']);
                    header('Location: ' . $basePath . '/cart.php', true, 303);
                    exit;
                } catch (DomainException $exception) {
                    $errorMessage = $exception->getMessage();
                }
            }
        }
    }

    $cartData = $cart->pageData((int) $user['id']);
    $_SESSION['cart_item_count'] = $cartData['item_count'];
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore cart database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
} catch (RuntimeException $exception) {
    error_log('Bookstore cart error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'cart.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
