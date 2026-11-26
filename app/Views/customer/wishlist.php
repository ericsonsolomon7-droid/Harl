<?php
use function Bookstore\Support\bookCoverPath;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;

$activeAccountPage = 'wishlist';
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">BOOKS TO COME BACK TO</span>
    <h1>Your wishlist</h1>
    <p>Save a title now and find it easily when you are ready.</p>
  </header>
  <?php require __DIR__ . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
  <?php if ($flashMessage !== null): ?>
    <p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p>
  <?php endif; ?>
  <?php if ($errorMessage !== ''): ?>
    <p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p>
  <?php endif; ?>

  <?php if ($wishlistItems === []): ?>
    <section class="customer-panel customer-empty">
      <h2>Your reading list is ready for a first pick.</h2>
      <p>Browse the catalogue and save books you would like to explore later.</p>
      <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/books.php">Browse books <span aria-hidden="true">→</span></a>
    </section>
  <?php else: ?>
    <section class="wishlist-list" aria-label="Saved books">
      <?php foreach ($wishlistItems as $index => $book): ?>
        <?php $coverPath = bookCoverPath($book['cover_image']); $price = $book['discount_price'] !== null ? $book['discount_price'] : $book['price']; $bookAvailable = $book['status'] === 'active'; ?>
        <article class="wishlist-item">
          <?php if ($bookAvailable): ?><a class="wishlist-cover cover-tone-<?= $index % 6 ?>" href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>"><?php else: ?><div class="wishlist-cover cover-tone-<?= $index % 6 ?>"><?php endif; ?>
            <?php if ($coverPath !== null): ?>
              <img src="<?= escapeHtml($basePath . '/' . $coverPath) ?>" alt="Cover of <?= escapeHtml($book['title']) ?>" loading="lazy">
            <?php else: ?>
              <span class="cover-initial" aria-hidden="true"><?= escapeHtml(strtoupper(substr((string) $book['title'], 0, 1))) ?></span>
              <span class="cover-title"><?= escapeHtml($book['title']) ?></span>
            <?php endif; ?>
          <?php if ($bookAvailable): ?></a><?php else: ?></div><?php endif; ?>
          <div class="wishlist-copy">
            <span class="eyebrow"><?= escapeHtml($book['authors'] ?: 'Independent author') ?></span>
            <h2><?php if ($bookAvailable): ?><a href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>"><?php endif; ?><?= escapeHtml($book['title']) ?><?php if ($bookAvailable): ?></a><?php endif; ?></h2>
            <p class="book-price"><?= escapeHtml(formatGhs($price)) ?></p>
            <p class="stock-status <?= $bookAvailable && (int) $book['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>"><?= !$bookAvailable ? 'No longer available' : ((int) $book['stock_quantity'] > 0 ? 'In stock' : 'Currently out of stock') ?></p>
          </div>
          <div class="wishlist-actions">
            <?php if ($bookAvailable): ?><a class="text-link" href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $book['slug']) ?>">View book <span aria-hidden="true">→</span></a><?php endif; ?>
            <form method="post" action="<?= escapeHtml($basePath) ?>/wishlist.php">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
              <button class="text-button text-button-danger" type="submit">Remove</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
