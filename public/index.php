<?php
declare(strict_types=1);

use function Bookstore\Config\database;
use function Bookstore\Config\bootstrap;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\limitText;
use Bookstore\Services\BookCatalogue;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookCatalogue.php';

bootstrap();

$rawSearch = $_GET['q'] ?? '';
$searchQuery = is_string($rawSearch) ? limitText(trim($rawSearch), 120) : '';
$rawCategory = $_GET['category'] ?? '';
$selectedCategory = is_string($rawCategory) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $rawCategory)
    ? $rawCategory
    : '';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');

try {
    $pdo = database();
    $categories = BookCatalogue::categories($pdo);
    $books = BookCatalogue::find($pdo, [
        'search' => $searchQuery,
        'category' => $selectedCategory,
        'sort' => 'newest',
    ], 8, 0);
    $bestSellers = $searchQuery === '' && $selectedCategory === '' ? BookCatalogue::bestSellers($pdo, 4) : [];
} catch (\PDOException $exception) {
    error_log('Bookstore storefront database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

$pageTitle = $searchQuery !== '' ? 'Search results' : ($selectedCategory !== '' ? 'Books by category' : 'Find your next favorite');
$isFiltered = $searchQuery !== '' || $selectedCategory !== '';

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
<main id="main-content">
  <section class="hero">
    <div class="hero-copy">
      <span class="eyebrow">A BOOKSHOP FOR THE CURIOUS</span>
      <h1>Somewhere between the pages, <em>you’ll find it.</em></h1>
      <p>Meet your next favorite story. Explore thoughtful reads from Ghana and around the world.</p>
      <a class="button button-dark" href="#new-arrivals">Explore the shelves <span aria-hidden="true">→</span></a>
      <div class="hero-note"><span class="hero-note-mark">✳</span> Carefully chosen. Happily read.</div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="sun-disc"></div>
      <div class="book-stack">
        <div class="book book-back"><span>THE ART OF<br>BEGINNING</span></div>
        <div class="book book-middle"><span>WIDE<br>OPEN<br>WORLDS</span></div>
        <div class="book book-front"><span>A GOOD<br>STORY<br>STAYS</span><i>THE READING ROOM</i></div>
      </div>
      <span class="art-caption">THE JOY OF GETTING LOST IN A BOOK</span>
    </div>
  </section>

  <section class="service-strip" aria-label="Bookshop details">
    <div><span class="service-icon">01</span><span>Books for every kind of curious</span></div>
    <div><span class="service-icon">02</span><span>Local stories, global perspectives</span></div>
    <div><span class="service-icon">03</span><span>Made for readers in Ghana</span></div>
  </section>

  <section class="section category-section" id="categories">
    <div class="section-heading">
      <div><span class="eyebrow">FOLLOW A THREAD</span><h2>Browse by mood</h2></div>
      <span class="heading-aside">A good place to start</span>
    </div>
    <?php if ($categories === []): ?>
      <p class="empty-note">Our shelves are being arranged. Please check back soon.</p>
    <?php else: ?>
      <div class="category-grid">
        <?php foreach ($categories as $index => $category): ?>
          <a class="category-tile category-tone-<?= $index % 4 ?>" href="<?= escapeHtml($basePath) ?>/books.php?category=<?= rawurlencode((string) $category['slug']) ?>">
            <span class="category-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <span class="category-name"><?= escapeHtml($category['name']) ?></span>
            <span class="category-count"><?= (int) $category['book_count'] ?> <?= (int) $category['book_count'] === 1 ? 'book' : 'books' ?></span>
            <span class="category-arrow" aria-hidden="true">↗</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="section collection-section" id="new-arrivals">
    <div class="section-heading">
      <div>
        <span class="eyebrow"><?= $isFiltered ? 'YOUR DISCOVERY' : 'FRESH OFF THE SHELF' ?></span>
        <h2><?= $searchQuery !== '' ? 'Search results' : ($selectedCategory !== '' ? 'Books in this category' : 'New arrivals') ?></h2>
      </div>
      <?php if ($isFiltered): ?>
        <a class="text-link" href="<?= escapeHtml($basePath) ?>/">Clear filters <span aria-hidden="true">×</span></a>
      <?php else: ?>
        <a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">See all books <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>
    <?php if ($searchQuery !== ''): ?>
      <p class="results-summary">Showing books matching “<?= escapeHtml($searchQuery) ?>”.</p>
    <?php elseif ($selectedCategory !== ''): ?>
      <p class="results-summary">Browse the titles in your chosen category.</p>
    <?php endif; ?>
    <?php if ($books === []): ?>
      <div class="empty-state">
        <span class="empty-state-mark" aria-hidden="true">R</span>
        <h3>No books found just yet.</h3>
        <p>Try another search, or return to the full collection.</p>
        <a class="text-link" href="<?= escapeHtml($basePath) ?>/">Browse all books <span aria-hidden="true">→</span></a>
      </div>
    <?php else: ?>
      <div class="book-grid">
        <?php foreach ($books as $index => $book): ?>
          <?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'book-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!$isFiltered): ?>
    <section class="bestseller-band" id="bestsellers">
      <div class="bestseller-intro">
        <span class="eyebrow">READER-LOVED</span>
        <h2>Stories finding<br>their way home.</h2>
        <p>Our best sellers, ranked from orders placed in the shop.</p>
      </div>
      <?php if ($bestSellers === []): ?>
        <div class="bestseller-empty">
          <span aria-hidden="true">✳</span>
          <p>Our reader favorites will show up here as orders come in.</p>
        </div>
      <?php else: ?>
        <div class="bestseller-list">
          <?php foreach ($bestSellers as $index => $book): ?>
            <article class="bestseller-item">
              <span class="bestseller-rank"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <h3><a href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>"><?= escapeHtml($book['title']) ?></a></h3>
                <p><?= escapeHtml($book['authors'] ?: 'Independent author') ?> · <?= (int) $book['units_sold'] ?> sold</p>
              </div>
              <span><?= escapeHtml(formatGhs($book['discount_price'] ?? $book['price'])) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="newsletter-callout">
    <div><span class="eyebrow">A NOTE FROM THE BOOKSHOP</span><h2>Good reads, now and then.</h2></div>
    <p>We’re building a quiet corner for book lovers. Explore the shelves and find a story to take home.</p>
    <a class="button button-light" href="#new-arrivals">Find a book <span aria-hidden="true">→</span></a>
  </section>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
