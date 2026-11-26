<?php
declare(strict_types=1);

use Bookstore\Services\BookCatalogue;
use function Bookstore\Config\database;
use function Bookstore\Config\bootstrap;
use function Bookstore\Support\bookCoverPath;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'view.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'BookCatalogue.php';

bootstrap();

$rawSlug = $_GET['slug'] ?? '';
$slug = is_string($rawSlug) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $rawSlug) ? $rawSlug : '';
$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
$pageTitle = 'Book details';
$searchQuery = '';

if ($slug === '') {
    http_response_code(404);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'not-found.php';
    exit;
}

try {
    $pdo = database();
    $book = BookCatalogue::findBySlug($pdo, $slug);
    if ($book !== null) {
        $viewUpdate = $pdo->prepare('UPDATE books SET view_count = view_count + 1 WHERE id = :book_id');
        $viewUpdate->execute(['book_id' => (int) $book['id']]);
        $reviews = BookCatalogue::approvedReviews($pdo, (int) $book['id']);
        $relatedBooks = $book['category_id'] === null
            ? []
            : BookCatalogue::find($pdo, [
                'category_id' => (int) $book['category_id'],
                'exclude_id' => (int) $book['id'],
                'sort' => 'popular',
            ], 4, 0);
    } else {
        $reviews = [];
        $relatedBooks = [];
    }
} catch (\PDOException $exception) {
    error_log('Book detail database error: ' . $exception->getMessage());
    http_response_code(503);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'database-unavailable.php';
    exit;
}

if ($book === null) {
    http_response_code(404);
    require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'not-found.php';
    exit;
}

$pageTitle = (string) $book['title'];
$viewMode = 'grid';
$coverPath = bookCoverPath($book['cover_image']);
$effectivePrice = $book['discount_price'] !== null ? $book['discount_price'] : $book['price'];

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
<main id="main-content" class="book-detail-page">
  <nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="<?= escapeHtml($basePath) ?>/">Home</a><span aria-hidden="true">/</span>
    <a href="<?= escapeHtml($basePath) ?>/books.php">Books</a><span aria-hidden="true">/</span>
    <span aria-current="page"><?= escapeHtml($book['title']) ?></span>
  </nav>

  <section class="book-detail">
    <div class="detail-cover cover-tone-<?= (int) $book['id'] % 6 ?>">
      <?php if ($coverPath !== null): ?>
        <img src="<?= escapeHtml($basePath . '/' . $coverPath) ?>" alt="Cover of <?= escapeHtml($book['title']) ?>">
      <?php else: ?>
        <span class="cover-category"><?= escapeHtml($book['category_name'] ?? 'BOOKS') ?></span>
        <span class="detail-cover-initial" aria-hidden="true"><?= escapeHtml(strtoupper(substr((string) $book['title'], 0, 1))) ?></span>
        <span class="detail-cover-title"><?= escapeHtml($book['title']) ?></span>
        <span class="detail-cover-author"><?= escapeHtml($book['authors'] ?: 'Independent author') ?></span>
      <?php endif; ?>
    </div>
    <div class="detail-copy">
      <span class="eyebrow"><?= escapeHtml($book['category_name'] ?? 'THE READING ROOM COLLECTION') ?></span>
      <h1><?= escapeHtml($book['title']) ?></h1>
      <p class="detail-author">by <?= escapeHtml($book['authors'] ?: 'Independent author') ?></p>
      <p class="detail-rating">
        <?php if ((int) $book['review_count'] > 0): ?>
          <span aria-hidden="true">★</span> <?= number_format((float) $book['average_rating'], 1) ?> / 5
          <a href="#reviews">(<?= (int) $book['review_count'] ?> <?= (int) $book['review_count'] === 1 ? 'review' : 'reviews' ?>)</a>
        <?php else: ?>
          <span class="rating-empty">No reviews yet</span>
        <?php endif; ?>
      </p>
      <div class="detail-price"><?= escapeHtml(formatGhs($effectivePrice)) ?>
        <?php if ($book['discount_price'] !== null): ?>
          <del><?= escapeHtml(formatGhs($book['price'])) ?></del>
        <?php endif; ?>
      </div>
      <p class="detail-availability <?= (int) $book['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
        <?= (int) $book['stock_quantity'] > 0 ? 'Available · ' . (int) $book['stock_quantity'] . ' in stock' : 'Currently out of stock' ?>
      </p>
      <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'customer'): ?>
        <?php if ((int) $book['stock_quantity'] > 0): ?>
          <form class="cart-add-form" method="post" action="<?= escapeHtml($basePath) ?>/cart.php">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
            <label for="book-quantity">Quantity</label>
            <input id="book-quantity" name="quantity" type="number" min="1" max="<?= min(99, (int) $book['stock_quantity']) ?>" value="1" required>
            <button class="button button-dark" type="submit">Add to cart <span aria-hidden="true">→</span></button>
          </form>
        <?php endif; ?>
        <form class="wishlist-add-form" method="post" action="<?= escapeHtml($basePath) ?>/wishlist.php">
          <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
          <button class="button button-outline" type="submit">Save to wishlist <span aria-hidden="true">♡</span></button>
        </form>
      <?php else: ?>
        <a class="button button-dark cart-signin-link" href="<?= escapeHtml($basePath) ?>/login.php">Sign in to add to cart <span aria-hidden="true">→</span></a>
        <a class="text-link wishlist-signin-link" href="<?= escapeHtml($basePath) ?>/login.php">Sign in to save this book <span aria-hidden="true">→</span></a>
      <?php endif; ?>
      <div class="detail-description"><?= nl2br(escapeHtml($book['description'])) ?></div>
      <dl class="book-metadata">
        <div><dt>ISBN</dt><dd><?= escapeHtml($book['isbn']) ?></dd></div>
        <div><dt>Publisher</dt><dd><?= escapeHtml($book['publisher_name'] ?? 'Not listed') ?></dd></div>
        <div><dt>Published</dt><dd><?= escapeHtml($book['publication_date'] ?? 'Not listed') ?></dd></div>
        <div><dt>Language</dt><dd><?= escapeHtml($book['language']) ?></dd></div>
        <?php if ($book['page_count'] !== null): ?>
          <div><dt>Pages</dt><dd><?= (int) $book['page_count'] ?></dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </section>

  <section class="section detail-reviews" id="reviews">
    <div class="section-heading">
      <div><span class="eyebrow">FROM THE READING COMMUNITY</span><h2>Reader reviews</h2></div>
      <?php if ((int) $book['review_count'] > 0): ?>
        <span class="heading-aside"><?= number_format((float) $book['average_rating'], 1) ?> out of 5 · <?= (int) $book['review_count'] ?> approved</span>
      <?php endif; ?>
    </div>
    <?php if ($reviews === []): ?>
      <p class="empty-note">There are no approved reviews for this title yet.</p>
    <?php else: ?>
      <div class="review-list">
        <?php foreach ($reviews as $review): ?>
          <article class="review-item">
            <div class="review-meta">
              <strong><?= escapeHtml($review['first_name'] . ' ' . $review['last_name']) ?></strong>
              <span class="review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?><?= str_repeat('☆', 5 - (int) $review['rating']) ?></span>
              <time datetime="<?= escapeHtml($review['created_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($review['created_at']))) ?></time>
            </div>
            <?php if ($review['review_text'] !== null && $review['review_text'] !== ''): ?>
              <p><?= nl2br(escapeHtml($review['review_text'])) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($relatedBooks !== []): ?>
    <section class="section related-section">
      <div class="section-heading"><div><span class="eyebrow">KEEP EXPLORING</span><h2>Related reads</h2></div></div>
      <div class="book-grid">
        <?php foreach ($relatedBooks as $index => $relatedBook): ?>
          <?php $book = $relatedBook; require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'book-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
