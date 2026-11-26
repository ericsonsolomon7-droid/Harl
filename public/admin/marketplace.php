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
$section = is_string($_GET['section'] ?? null) && in_array($_GET['section'], ['sellers', 'books'], true) ? $_GET['section'] : 'sellers';
$sellerStatuses = ['pending', 'under_review', 'approved', 'rejected', 'suspended'];
$bookStatuses = ['pending_review', 'under_review', 'approved', 'rejected', 'revision_requested', 'suspended', 'archived'];
$status = is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '';
$status = in_array($status, $section === 'sellers' ? $sellerStatuses : $bookStatuses, true) ? $status : null;
$search = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 100) : '';
$pageSize = 20;
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;
$csrfToken = csrfToken();
$errorMessage = '';

try {
    $pdo = database();
    $administrator = requireRole($pdo, 'admin', dirname($basePath) . '/login.php');
    $operations = new Administration($pdo);

    if ($requestMethod === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errorMessage = 'This form expired. Refresh the page and try again.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            $reason = is_string($_POST['reason'] ?? null) ? trim(substr($_POST['reason'], 0, 2000)) : '';
            try {
                if (in_array($action, ['approve_seller', 'review_seller', 'reject_seller', 'suspend_seller'], true)) {
                    $sellerId = filter_var($_POST['seller_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($sellerId === false) {
                        throw new DomainException('Choose a valid seller application.');
                    }
                    $sellerAction = substr($action, 0, -7);
                    $operations->reviewSeller((int) $administrator['id'], (int) $sellerId, $sellerAction, $reason, '');
                    setFlashMessage('success', 'The seller application was updated.');
                    $section = 'sellers';
                } elseif (in_array($action, ['approve_book', 'reject_book', 'revision_book', 'suspend_book'], true)) {
                    $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($bookId === false) {
                        throw new DomainException('Choose a valid book submission.');
                    }
                    $bookAction = match ($action) {
                        'approve_book' => 'approve', 'reject_book' => 'reject', 'revision_book' => 'revision', default => 'suspend',
                    };
                    $operations->reviewBook((int) $administrator['id'], (int) $bookId, $bookAction, $reason);
                    setFlashMessage('success', 'The book submission was updated.');
                    $section = 'books';
                } else {
                    throw new DomainException('Choose a valid marketplace action.');
                }
                $query = http_build_query(['section' => $section, 'status' => $status, 'q' => $search]);
                header('Location: ' . $basePath . '/marketplace.php?' . $query, true, 303);
                exit;
            } catch (DomainException $exception) {
                $errorMessage = $exception->getMessage();
            }
        }
    }

    if ($section === 'sellers') {
        $totalItems = $operations->countSellerApplications($status, $search);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = min($page, $totalPages);
        $items = $operations->sellerApplications($status, $search, $pageSize, ($page - 1) * $pageSize);
    } else {
        $totalItems = $operations->countBookSubmissions($status, $search);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $page = min($page, $totalPages);
        $items = $operations->bookSubmissions($status, $search, $pageSize, ($page - 1) * $pageSize);
    }
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore marketplace administration database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

$pageTitle = 'Marketplace management';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'marketplace.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'footer.php';
