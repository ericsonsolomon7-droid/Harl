<?php
use function Bookstore\Support\bookCoverPath;
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;

$title = (string) $book['title'];
$author = $book['authors'] ?: 'Independent author';
$effectivePrice = $book['discount_price'] !== null ? $book['discount_price'] : $book['price'];
$coverPath = bookCoverPath($book['cover_image'] ?? null);
?>
<article class="book-card<?= ($viewMode ?? 'grid') === 'list' ? ' book-card-list' : '' ?>">
  <a class="book-cover cover-tone-<?= $index % 6 ?>" href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>" aria-label="View <?= escapeHtml($title) ?>">
    <?php if ($coverPath !== null): ?>
      <img src="<?= escapeHtml($basePath . '/' . $coverPath) ?>" alt="Cover of <?= escapeHtml($title) ?>" loading="lazy">
    <?php else: ?>
      <span class="cover-category"><?= escapeHtml($book['category_name'] ?? 'BOOKS') ?></span>
      <span class="cover-initial" aria-hidden="true"><?= escapeHtml(strtoupper(substr($title, 0, 1))) ?></span>
      <span class="cover-title"><?= escapeHtml($title) ?></span>
      <span class="cover-author"><?= escapeHtml($author) ?></span>
    <?php endif; ?>
  </a>
  <div class="book-info">
    <span class="book-category"><?= escapeHtml($book['category_name'] ?? 'Books') ?></span>
    <h3><a href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>"><?= escapeHtml($title) ?></a></h3>
    <p class="book-author"><?= escapeHtml($author) ?></p>
    <div class="book-rating" aria-label="<?= (int) ($book['review_count'] ?? 0) ?> reviews">
      <?php if ((int) ($book['review_count'] ?? 0) > 0): ?>
        <span aria-hidden="true">★</span> <?= number_format((float) $book['average_rating'], 1) ?>
        <span class="rating-count">(<?= (int) $book['review_count'] ?>)</span>
      <?php else: ?>
        <span class="rating-empty">No reviews yet</span>
      <?php endif; ?>
    </div>
    <div class="book-card-bottom">
      <span class="book-price"><?= escapeHtml(formatGhs($effectivePrice)) ?>
        <?php if ($book['discount_price'] !== null): ?>
          <del><?= escapeHtml(formatGhs($book['price'])) ?></del>
        <?php endif; ?>
      </span>
      <span class="stock-status <?= (int) $book['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
        <?= (int) $book['stock_quantity'] > 0 ? 'In stock' : 'Sold out' ?>
      </span>
    </div>
    <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'customer' && (int) $book['stock_quantity'] > 0): ?>
      <form class="card-cart-form" method="post" action="<?= escapeHtml($basePath) ?>/cart.php">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <input type="hidden" name="quantity" value="1">
        <button type="submit">Add to cart <span aria-hidden="true">+</span></button>
      </form>
    <?php endif; ?>
    <a class="book-details-link" href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>">View details <span aria-hidden="true">→</span></a>
  </div>
</article>
