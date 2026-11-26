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
$pageTitle = 'Catalogue management';
$search = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 100) : '';
$rawPage = $_GET['page'] ?? '1';
$page = is_string($rawPage) && ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$csrfToken = csrfToken();
$errorMessage = '';

try {
    $pdo = database();
    $administrator = requireRole($pdo, 'admin', '/login.php');
    $operations = new Administration($pdo);

    if ($requestMethod === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            $errorMessage = 'This form expired. Refresh the page and try again.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
            if ($action === 'delete') {
                $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($bookId === false) {
                    throw new DomainException('Choose a valid book.');
                }
                $operations->deleteCatalogueBook((int) $bookId);
                setFlashMessage('success', 'The book was deleted or archived if it has existing orders.');
                header('Location: ' . $basePath . '/books.php', true, 303);
                exit;
            }

            if ($action === 'save') {
                $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $bookId = $bookId === false ? null : (int) $bookId;
                $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $pageCount = filter_var($_POST['page_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
                $discountPrice = trim((string) ($_POST['discount_price'] ?? ''));
                $discountPrice = $discountPrice === '' ? null : filter_var($discountPrice, FILTER_VALIDATE_FLOAT);
                $stock = filter_var($_POST['stock_quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                $threshold = filter_var($_POST['low_stock_threshold'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                $title = substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
                $author = substr(trim((string) ($_POST['author_text'] ?? '')), 0, 255);
                $isbn = strtoupper(trim((string) ($_POST['isbn'] ?? '')));
                $slug = substr(trim((string) ($_POST['slug'] ?? '')), 0, 280);
                $description = trim((string) ($_POST['description'] ?? ''));
                $language = substr(trim((string) ($_POST['language'] ?? 'English')), 0, 60);
                $publicationDate = trim((string) ($_POST['publication_date'] ?? ''));
                $coverImage = substr(trim((string) ($_POST['cover_image'] ?? '')), 0, 500);
                $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : 'draft';

                if ($title === '' || $author === '' || $isbn === '' || $slug === '' || $description === '') {
                    throw new DomainException('Complete title, author, ISBN, slug, and description.');
                }
                if (!preg_match('/^[A-Za-z0-9-]{5,20}$/', $isbn)) {
                    throw new DomainException('Enter a valid ISBN using 5 to 20 letters or numbers.');
                }
                if ($categoryId === false || $price === false || $stock === false || $threshold === false) {
                    throw new DomainException('Choose a valid category and enter valid price and stock values.');
                }
                if ($discountPrice === false || (float) $price < 0 || ($discountPrice !== null && (float) $discountPrice >= (float) $price)) {
                    throw new DomainException('Enter a valid discount price below the regular price.');
                }
                if (!in_array($status, ['draft', 'active', 'archived'], true)) {
                    throw new DomainException('Choose a valid catalogue status.');
                }

                $operations->saveCatalogueBook($bookId, [
                    'title' => $title, 'author_text' => $author, 'isbn' => $isbn,
                    'slug' => $slug, 'description' => $description,
                    'category_id' => (int) $categoryId, 'publication_date' => $publicationDate !== '' ? $publicationDate : null,
                    'language' => $language !== '' ? $language : 'English',
                    'page_count' => $pageCount === false ? null : (int) $pageCount,
                    'price' => number_format((float) $price, 2, '.', ''),
                    'discount_price' => $discountPrice === null ? null : number_format((float) $discountPrice, 2, '.', ''),
                    'stock_quantity' => (int) $stock, 'low_stock_threshold' => (int) $threshold,
                    'status' => $status, 'cover_image' => $coverImage !== '' ? $coverImage : null,
                ]);
                setFlashMessage('success', $bookId === null ? 'The book was added to the catalogue.' : 'The book was updated.');
                header('Location: ' . $basePath . '/books.php', true, 303);
                exit;
            }
        }
    }

    $categories = $operations->catalogueCategories();
    $editBook = $editId !== false ? $operations->catalogueBook((int) $editId) : null;
    $pageSize = 20;
    $totalBooks = $operations->countCatalogueBooks($search);
    $totalPages = max(1, (int) ceil($totalBooks / $pageSize));
    $page = min($page, $totalPages);
    $books = $operations->catalogueBooks($search, $pageSize, ($page - 1) * $pageSize);
    $flashMessage = consumeFlashMessage();
} catch (DomainException $exception) {
    $errorMessage = $exception->getMessage();
    $categories = $operations->catalogueCategories();
    $editBook = $editId !== false ? $operations->catalogueBook((int) $editId) : null;
    $pageSize = 20;
    $totalBooks = $operations->countCatalogueBooks($search);
    $totalPages = max(1, (int) ceil($totalBooks / $pageSize));
    $page = min($page, $totalPages);
    $books = $operations->catalogueBooks($search, $pageSize, ($page - 1) * $pageSize);
    $flashMessage = consumeFlashMessage();
} catch (PDOException $exception) {
    error_log('Bookstore administration catalogue database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'header.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'books.php';
require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'footer.php';
