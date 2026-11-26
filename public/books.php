<?php
declare(strict_types=1);

use Bookstore\Services\BookCatalogue;
use function Bookstore\Config\database;
use function Bookstore\Config\bootstrap;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
use function Bookstore\Support\limitText;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookCatalogue.php';

bootstrap();

function requestText(string $key, int $maxLength = 120): string
{
    $value = $_GET[$key] ?? '';

    return is_string($value) ? limitText(trim($value), $maxLength) : '';
}

function requestPrice(string $key, array &$errors): ?string
{
    $value = requestText($key, 16);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
        $errors[] = 'Enter a valid price amount using up to two decimal places.';
        return null;
    }

    return number_format((float) $value, 2, '.', '');
}

$errors = [];
$searchQuery = requestText('q');
$category = requestText('category', 150);
if ($category !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $category)) {
    $errors[] = 'Choose a valid category.';
    $category = '';
}

$publisherInput = requestText('publisher', 20);
$publisherId = null;
if ($publisherInput !== '') {
    $validatedPublisherId = filter_var($publisherInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedPublisherId === false) {
        $errors[] = 'Choose a valid publisher.';
    } else {
        $publisherId = $validatedPublisherId;
    }
}

$minPrice = requestPrice('min_price', $errors);
$maxPrice = requestPrice('max_price', $errors);
if ($minPrice !== null && $maxPrice !== null && (float) $minPrice > (float) $maxPrice) {
    $errors[] = 'Minimum price cannot be greater than maximum price.';
    $minPrice = null;
    $maxPrice = null;
}

$minRating = requestText('rating', 4);
if (!in_array($minRating, ['', '3', '4', '4.5', '5'], true)) {
    $errors[] = 'Choose a valid minimum rating.';
    $minRating = '';
}

$sort = requestText('sort', 20);
if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'popular'], true)) {
    $sort = 'newest';
}
$viewMode = requestText('view', 8);
if (!in_array($viewMode, ['grid', 'list'], true)) {
    $viewMode = 'grid';
}
$pageInput = requestText('page', 10);
$page = filter_var($pageInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');

$filters = [
    'search' => $searchQuery,
    'category' => $category,
    'publisher_id' => $publisherId,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'min_rating' => $minRating,
    'sort' => $sort,
];

try {
    $pdo = database();
    $categories = BookCatalogue::categories($pdo);
    $publishers = BookCatalogue::publishers($pdo);
    $totalBooks = BookCatalogue::count($pdo, $filters);
    $pageSize = 12;
    $pageCount = max(1, (int) ceil($totalBooks / $pageSize));
    $page = min($page, $pageCount);
    $books = BookCatalogue::find($pdo, $filters, $pageSize, ($page - 1) * $pageSize);
    $pageNumbers = [];
    if ($pageCount > 1) {
        $startPage = max(1, $page - 2);
        $endPage = min($pageCount, $page + 2);
        $pageNumbers = range($startPage, $endPage);
        if ($startPage > 1) {
            $pageNumbers = array_merge([1], $startPage > 2 ? ['…'] : [], $pageNumbers);
        }
        if ($endPage < $pageCount) {
            $pageNumbers = array_merge($pageNumbers, $endPage < $pageCount - 1 ? ['…'] : [], [$pageCount]);
        }
    }
} catch (\PDOException $exception) {
    error_log('Bookstore catalogue database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

$pageTitle = 'Browse books';
$queryParameters = [
    'q' => $searchQuery,
    'category' => $category,
    'publisher' => $publisherId,
    'min_price' => $minPrice,
    'max_price' => $maxPrice,
    'rating' => $minRating,
    'sort' => $sort,
    'view' => $viewMode,
];
$queryParameters = array_filter($queryParameters, static fn ($value): bool => $value !== null && $value !== '');

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
<main id="main-content" class="catalogue-page">
  <section class="page-intro">
    <span class="eyebrow">THE BOOKSHELF</span>
    <h1>Find a story to take home.</h1>
    <p>Search the collection, follow a favorite author, or see where a new subject takes you.</p>
  </section>
  <div class="catalogue-layout">
    <aside class="catalogue-filters" id="filters">
      <div class="filter-heading">
        <h2>Refine your search</h2>
        <a href="<?= escapeHtml($basePath) ?>/books.php">Reset</a>
      </div>
      <?php if ($errors !== []): ?>
        <div class="form-errors" role="alert">
          <?php foreach (array_unique($errors) as $error): ?>
            <p><?= escapeHtml($error) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form method="get" action="<?= escapeHtml($basePath) ?>/books.php">
        <label class="filter-label" for="catalogue-search">Search</label>
        <input class="filter-input" id="catalogue-search" type="search" name="q" value="<?= escapeHtml($searchQuery) ?>" maxlength="120" placeholder="Title, author, ISBN">

        <label class="filter-label" for="category">Category</label>
        <select class="filter-input" id="category" name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $item): ?>
            <option value="<?= escapeHtml($item['slug']) ?>" <?= $category === $item['slug'] ? 'selected' : '' ?>><?= escapeHtml($item['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="filter-label" for="publisher">Publisher</label>
        <select class="filter-input" id="publisher" name="publisher">
          <option value="">All publishers</option>
          <?php foreach ($publishers as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $publisherId === (int) $item['id'] ? 'selected' : '' ?>><?= escapeHtml($item['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <span class="filter-label">Price range (GH₵)</span>
        <div class="price-fields">
          <label><span class="sr-only">Minimum price</span><input class="filter-input" type="number" name="min_price" min="0" step="0.01" value="<?= escapeHtml(requestText('min_price', 16)) ?>" placeholder="Min"></label>
          <label><span class="sr-only">Maximum price</span><input class="filter-input" type="number" name="max_price" min="0" step="0.01" value="<?= escapeHtml(requestText('max_price', 16)) ?>" placeholder="Max"></label>
        </div>

        <label class="filter-label" for="rating">Minimum rating</label>
        <select class="filter-input" id="rating" name="rating">
          <option value="">Any rating</option>
          <option value="3" <?= $minRating === '3' ? 'selected' : '' ?>>3 stars and up</option>
          <option value="4" <?= $minRating === '4' ? 'selected' : '' ?>>4 stars and up</option>
          <option value="4.5" <?= $minRating === '4.5' ? 'selected' : '' ?>>4.5 stars and up</option>
          <option value="5" <?= $minRating === '5' ? 'selected' : '' ?>>5 stars</option>
        </select>

        <input type="hidden" name="sort" value="<?= escapeHtml($sort) ?>">
        <input type="hidden" name="view" value="<?= escapeHtml($viewMode) ?>">
        <button class="button button-dark filter-submit" type="submit">Apply filters <span aria-hidden="true">→</span></button>
      </form>
    </aside>

    <section class="catalogue-results" aria-labelledby="results-heading">
      <div class="results-toolbar">
        <div>
          <span class="eyebrow">A LITTLE SOMETHING FOR EVERY SHELF</span>
          <h2 id="results-heading">The collection</h2>
          <p><?= number_format($totalBooks) ?> <?= $totalBooks === 1 ? 'book' : 'books' ?> found</p>
        </div>
        <div class="results-controls">
          <form class="sort-control" method="get" action="<?= escapeHtml($basePath) ?>/books.php">
            <label for="sort">Sort</label>
            <?php foreach ($queryParameters as $key => $value): ?>
              <?php if ($key !== 'sort'): ?>
                <input type="hidden" name="<?= escapeHtml($key) ?>" value="<?= escapeHtml($value) ?>">
              <?php endif; ?>
            <?php endforeach; ?>
            <select id="sort" name="sort">
              <?php
              $sortLabels = ['newest' => 'Newest', 'popular' => 'Most popular', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];
              foreach ($sortLabels as $sortValue => $sortLabel):
              ?>
                <option value="<?= escapeHtml($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= escapeHtml($sortLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Go</button>
          </form>
          <div class="view-switch" aria-label="Catalogue layout">
            <?php foreach (['grid' => 'Grid', 'list' => 'List'] as $mode => $label): ?>
              <?php $modeParams = $queryParameters; $modeParams['view'] = $mode; $modeParams['page'] = 1; ?>
              <a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query($modeParams)) ?>" class="<?= $viewMode === $mode ? 'is-selected' : '' ?>" aria-current="<?= $viewMode === $mode ? 'page' : 'false' ?>"><?= escapeHtml($label) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if ($books === []): ?>
        <div class="empty-state">
          <span class="empty-state-mark" aria-hidden="true">R</span>
          <h3>No matching books found.</h3>
          <p>Try changing a filter or clearing your search.</p>
          <a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">See the full collection <span aria-hidden="true">→</span></a>
        </div>
      <?php else: ?>
        <div class="book-grid <?= $viewMode === 'list' ? 'book-list-view' : '' ?>">
          <?php foreach ($books as $index => $book): ?>
            <?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'book-card.php'; ?>
          <?php endforeach; ?>
        </div>
        <?php if ($pageCount > 1): ?>
          <nav class="pagination" aria-label="Book catalogue pages">
            <?php if ($page > 1): ?>
              <?php $pageParams = $queryParameters; $pageParams['page'] = $page - 1; ?>
              <a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query($pageParams)) ?>" rel="prev" aria-label="Previous page">‹</a>
            <?php endif; ?>
            <?php foreach ($pageNumbers as $pageNumber): ?>
              <?php if (!is_int($pageNumber)): ?>
              <span class="pagination-ellipsis" aria-hidden="true">…</span>
              <?php else: ?>
                <?php $pageParams = $queryParameters; $pageParams['page'] = $pageNumber; ?>
                <a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query($pageParams)) ?>" <?= $page === $pageNumber ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($page < $pageCount): ?>
              <?php $pageParams = $queryParameters; $pageParams['page'] = $page + 1; ?>
              <a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query($pageParams)) ?>" rel="next" aria-label="Next page">›</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
