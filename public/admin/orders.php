<?php
declare(strict_types=1);

use Bookstore\Services\Administration;
use function Bookstore\Config\bootstrap;
use function Bookstore\Config\database;
use function Bookstore\Middleware\requireRole;
use function Bookstore\Support\consumeFlashMessage;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\setFlashMessage;
use function Bookstore\Support\verifyCsrfToken;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'flash.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Middleware' . DIRECTORY_SEPARATOR . 'authorization.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'Administration.php';

bootstrap();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$pageTitle = 'Order management';
$errorMessage = '';
$pageSize = 25;
$rawStatus = $_GET['status'] ?? '';
$status = is_string($rawStatus) && in_array($rawStatus, ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'], true)
    ? $rawStatus
    : null;
$rawSearch = $_GET['q'] ?? '';
$search = is_string($rawSearch) ? substr(trim($rawSearch), 0, 100) : '';
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;
$csrfToken = csrfToken();

try {
    $pdo = database();
    $administrator = requireRole($pdo, 'admin', '/login.php');
    $operations = new Administration($pdo);

    if ($requestMethod === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errorMessage = 'This form expired. Refresh the page and try again.';
        } else {
            $action = $_POST['action'] ?? '';
            $orderIdInput = $_POST['order_id'] ?? '';
            $orderId = is_string($orderIdInput) && ctype_digit($orderIdInput) && (int) $orderIdInput > 0
                ? (int) $orderIdInput
                : null;
            if ($orderId === null) {
                http_response_code(400);
                $errorMessage = 'Choose a valid order.';
            } else {
                try {
                    if ($action === 'status') {
                        $newStatus = $_POST['order_status'] ?? '';
                        if (!is_string($newStatus)) {
                            throw new DomainException('Choose a valid order status.');
                        }
                        $operations->updateOrderStatus((int) $administrator['id'], $orderId, $newStatus);
                        setFlashMessage('success', 'The order status was updated.');
                    } elseif ($action === 'collect_cod') {
                        $operations->recordCodPayment((int) $administrator['id'], $orderId);
                        setFlashMessage('success', 'The Cash on Delivery collection was recorded.');
                    } else {
                        http_response_code(400);
                        throw new DomainException('Choose a valid order action.');
                    }

                    $query = http_build_query(array_filter([
                        'status' => $status,
                        'q' => $search,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
                    header('Location: ' . $basePath . '/orders.php' . ($query === '' ? '' : '?' . $query), true, 303);
                    exit;
                } catch (DomainException $exception) {
                    $errorMessage = $exception->getMessage();
                }
            }
        }
    }

    $totalOrders = $operations->countOrders($status, $search);
    $totalPages = max(1, (int) ceil($totalOrders / $pageSize));
    $page = min($page, $totalPages);
    $orders = $operations->orders($status, $search, $pageSize, ($page - 1) * $pageSize);
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore administration orders database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
} catch (RuntimeException $exception) {
    error_log('Bookstore administration orders error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
];
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'orders.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'footer.php';
