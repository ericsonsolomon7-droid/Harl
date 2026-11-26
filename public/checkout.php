<?php
declare(strict_types=1);

use Bookstore\Services\CustomerAccount;
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
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'CustomerAccount.php';
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
$pageTitle = 'Checkout';
$searchQuery = '';
$errorMessage = '';
$csrfToken = csrfToken();
$couponCode = '';
$selectedAddressId = 0;
$quote = null;
$confirmation = null;
$confirmationItems = [];
$addresses = [];

try {
    $pdo = database();
    $user = requireRole($pdo, 'customer', $basePath . '/login.php');
    $cart = new CustomerCart($pdo);
    $account = new CustomerAccount($pdo);

    $orderNumberInput = $_GET['order'] ?? null;
    if ($requestMethod === 'GET' && $orderNumberInput !== null) {
        if (!is_string($orderNumberInput) || preg_match('/^RR-[0-9]{6}-[A-F0-9]{10}$/D', $orderNumberInput) !== 1) {
            http_response_code(404);
            $errorMessage = 'That order could not be found.';
        } else {
            $confirmation = $cart->orderForCustomer((int) $user['id'], $orderNumberInput);
            if ($confirmation === null) {
                http_response_code(404);
                $errorMessage = 'That order could not be found.';
            } else {
                $confirmationItems = $cart->orderItemsForCustomer((int) $user['id'], $orderNumberInput);
                $pageTitle = 'Order confirmed';
                $_SESSION['cart_item_count'] = $cart->itemCount((int) $user['id']);
            }
        }
    } else {
        $addresses = $account->addresses((int) $user['id']);
        $rawAddressId = $requestMethod === 'POST' ? ($_POST['address_id'] ?? '') : ($_GET['address_id'] ?? '');
        if (is_string($rawAddressId) && ctype_digit($rawAddressId) && (int) $rawAddressId > 0) {
            $selectedAddressId = (int) $rawAddressId;
        } elseif ($requestMethod === 'GET') {
            foreach ($addresses as $address) {
                if ((int) $address['is_default'] === 1) {
                    $selectedAddressId = (int) $address['id'];
                    break;
                }
            }
            if ($selectedAddressId === 0 && $addresses !== []) {
                $selectedAddressId = (int) $addresses[0]['id'];
            }
        } elseif ($rawAddressId !== '') {
            http_response_code(400);
            $errorMessage = 'Choose a valid delivery address.';
        }

        if ($requestMethod === 'POST') {
            if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
                http_response_code(400);
                $errorMessage = 'This form expired. Refresh the page and try again.';
            } else {
                $action = $_POST['action'] ?? '';
                $rawCoupon = $_POST['coupon_code'] ?? '';
                $couponCode = is_string($rawCoupon) ? strtoupper(trim($rawCoupon)) : '';
                if (!is_string($rawCoupon) || ($couponCode !== '' && preg_match('/^[A-Z0-9_-]{1,50}$/D', $couponCode) !== 1)) {
                    $errorMessage = 'Enter a valid coupon code.';
                } elseif (!in_array($action, ['review', 'place_order'], true)) {
                    http_response_code(400);
                    $errorMessage = 'Choose a valid checkout action.';
                } elseif ($action === 'place_order') {
                    $cachedQuote = $_SESSION['checkout_quote'] ?? null;
                    $quoteIsCurrent = is_array($cachedQuote)
                        && isset($cachedQuote['address_id'], $cachedQuote['coupon_code'], $cachedQuote['created_at'])
                        && (int) $cachedQuote['address_id'] === $selectedAddressId
                        && $cachedQuote['coupon_code'] === $couponCode
                        && is_int($cachedQuote['created_at'])
                        && $cachedQuote['created_at'] >= time() - 900;
                    if (!$quoteIsCurrent) {
                        unset($_SESSION['checkout_quote']);
                        $errorMessage = 'Review the current address and order total before placing your order.';
                    } else {
                        $expectedQuote = $cachedQuote;
                        unset($expectedQuote['address_id'], $expectedQuote['coupon_code'], $expectedQuote['created_at']);
                        $orderNumber = $cart->placeOrder((int) $user['id'], $selectedAddressId, $couponCode, $expectedQuote);
                        unset($_SESSION['checkout_quote']);
                        $_SESSION['cart_item_count'] = 0;
                        setFlashMessage('success', 'Your order has been placed. Payment is due on delivery.');
                        header('Location: ' . $basePath . '/checkout.php?order=' . rawurlencode($orderNumber), true, 303);
                        exit;
                    }
                }
            }
        }

        if ($errorMessage === '' && $addresses === []) {
            $errorMessage = 'Add a delivery address before checkout.';
        }
        if ($errorMessage === '' && $selectedAddressId === 0 && $addresses !== []) {
            $errorMessage = 'Choose a delivery address to calculate your order total.';
        }
        if ($errorMessage === '' && $selectedAddressId > 0 && $cart->items((int) $user['id']) !== []) {
            try {
                $quote = $cart->quote((int) $user['id'], $selectedAddressId, $couponCode);
                $_SESSION['checkout_quote'] = [
                    'address_id' => $selectedAddressId,
                    'coupon_code' => $couponCode,
                    'subtotal_cents' => $quote['subtotal_cents'],
                    'discount_cents' => $quote['discount_cents'],
                    'delivery_cents' => $quote['delivery_cents'],
                    'tax_cents' => $quote['tax_cents'],
                    'total_cents' => $quote['total_cents'],
                    'coupon_id' => $quote['coupon_id'],
                    'currency' => $quote['currency'],
                    'items_signature' => $quote['items_signature'],
                    'created_at' => time(),
                ];
            } catch (DomainException $exception) {
                unset($_SESSION['checkout_quote']);
                $errorMessage = $exception->getMessage();
            }
        } elseif ($errorMessage !== '') {
            unset($_SESSION['checkout_quote']);
        }
        if ($cart->items((int) $user['id']) === []) {
            $errorMessage = $errorMessage !== '' ? $errorMessage : 'Your cart is empty.';
        }
        $_SESSION['cart_item_count'] = $cart->itemCount((int) $user['id']);
    }

    $flashMessage = consumeFlashMessage();
} catch (DomainException $exception) {
    $errorMessage = $exception->getMessage();
    $addresses = isset($addresses) ? $addresses : [];
    $cartData = ['items' => []];
    $flashMessage = null;
} catch (PDOException $exception) {
    error_log('Bookstore checkout database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
} catch (RuntimeException $exception) {
    error_log('Bookstore checkout error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

if ($confirmation !== null) {
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'order-confirmation.php';
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
    exit;
}

$cartData = isset($cart) && isset($user) ? $cart->pageData((int) $user['id']) : ['items' => [], 'subtotal' => '0.00', 'item_count' => 0];
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'customer' . DIRECTORY_SEPARATOR . 'checkout.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
