<?php
use function Bookstore\Support\bookCoverPath;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">YOUR NEXT GOOD READ</span>
    <h1>Shopping cart</h1>
    <p>Review your books and quantities before checkout.</p>
  </header>
  <?php if ($flashMessage !== null): ?><p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
  <?php if ($errorMessage !== ''): ?><p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p><?php endif; ?>

  <?php if ($cartData['items'] === []): ?>
    <section class="customer-panel customer-empty">
      <h2>Your cart is waiting for a story.</h2>
      <p>Browse the catalogue and add books when you find the right one.</p>
      <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/books.php">Explore books <span aria-hidden="true">→</span></a>
    </section>
  <?php else: ?>
    <section class="cart-layout">
      <div class="cart-items">
        <?php foreach ($cartData['items'] as $index => $item): ?>
          <?php $coverPath = bookCoverPath($item['cover_image']); $active = $item['status'] === 'active'; ?>
          <article class="cart-item">
            <?php if ($active): ?><a class="wishlist-cover cover-tone-<?= $index % 6 ?>" href="<?= escapeHtml($basePath) ?>/book.php?slug=<?= rawurlencode((string) $item['slug']) ?>"><?php else: ?><div class="wishlist-cover cover-tone-<?= $index % 6 ?>"><?php endif; ?>
              <?php if ($coverPath !== null): ?><img src="<?= escapeHtml($basePath . '/' . $coverPath) ?>" alt="Cover of <?= escapeHtml($item['title']) ?>" loading="lazy"><?php else: ?><span class="cover-initial" aria-hidden="true"><?= escapeHtml(strtoupper(substr((string) $item['title'], 0, 1))) ?></span><span class="cover-title"><?= escapeHtml($item['title']) ?></span><?php endif; ?>
            <?php if ($active): ?></a><?php else: ?></div><?php endif; ?>
            <div class="cart-item-copy">
              <span class="eyebrow">ISBN <?= escapeHtml($item['isbn']) ?></span>
              <h2><?= escapeHtml($item['title']) ?></h2>
              <p class="book-price"><?= escapeHtml(formatGhs($item['unit_price'])) ?> each</p>
              <p class="stock-status <?= $active && (int) $item['stock_quantity'] >= (int) $item['quantity'] ? 'in-stock' : 'out-of-stock' ?>">
                <?= !$active ? 'No longer available' : ((int) $item['stock_quantity'] < (int) $item['quantity'] ? 'Only ' . (int) $item['stock_quantity'] . ' currently in stock' : 'In stock') ?>
              </p>
              <div class="cart-item-actions">
                <?php if ($active && (int) $item['stock_quantity'] > 0): ?>
                  <form method="post" action="<?= escapeHtml($basePath) ?>/cart.php" class="cart-quantity-form">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="book_id" value="<?= (int) $item['book_id'] ?>">
                    <label class="sr-only" for="quantity-<?= (int) $item['book_id'] ?>">Quantity for <?= escapeHtml($item['title']) ?></label>
                    <input id="quantity-<?= (int) $item['book_id'] ?>" name="quantity" type="number" min="1" max="<?= min(99, (int) $item['stock_quantity']) ?>" value="<?= (int) $item['quantity'] ?>" required>
                    <button class="text-button" type="submit">Update</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= escapeHtml($basePath) ?>/cart.php">
                  <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="book_id" value="<?= (int) $item['book_id'] ?>">
                  <button class="text-button text-button-danger" type="submit">Remove</button>
                </form>
              </div>
            </div>
            <strong class="cart-line-total"><?= escapeHtml(formatGhs($item['line_total'])) ?></strong>
          </article>
        <?php endforeach; ?>
      </div>
      <aside class="cart-summary">
        <span class="eyebrow">ORDER SUMMARY</span>
        <h2>Subtotal</h2>
        <p><span><?= (int) $cartData['item_count'] ?> <?= (int) $cartData['item_count'] === 1 ? 'book' : 'books' ?></span><strong><?= escapeHtml(formatGhs($cartData['subtotal'])) ?></strong></p>
        <p class="cart-summary-note">Delivery and any applicable tax are calculated at checkout using your saved delivery address.</p>
        <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/checkout.php">Continue to checkout <span aria-hidden="true">→</span></a>
        <a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">Continue browsing</a>
      </aside>
    </section>
  <?php endif; ?>
</main>
