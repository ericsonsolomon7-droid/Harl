<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<main id="main-content" class="customer-page">
  <section class="customer-panel order-confirmation">
    <?php if ($flashMessage !== null): ?><p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
    <span class="eyebrow">ORDER RECEIVED</span>
    <h1>Thank you for your order.</h1>
    <p>Your order <strong><?= escapeHtml($confirmation['order_number']) ?></strong> is pending confirmation. Payment remains unpaid and is due on delivery.</p>
    <div class="order-confirmation-status">
      <span>Order status <strong><?= escapeHtml(ucfirst($confirmation['order_status'])) ?></strong></span>
      <span>Payment status <strong><?= escapeHtml(ucfirst($confirmation['payment_status'])) ?></strong></span>
    </div>
    <div class="orders-table-wrap">
      <table class="orders-table">
        <thead><tr><th>Book</th><th>Quantity</th><th>Unit price</th><th>Line total</th></tr></thead>
        <tbody>
          <?php foreach ($confirmationItems as $item): ?>
            <tr><td><?= escapeHtml($item['book_title']) ?></td><td><?= (int) $item['quantity'] ?></td><td><?= escapeHtml(formatGhs($item['unit_price'])) ?></td><td><?= escapeHtml(formatGhs($item['line_total'])) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <dl class="confirmation-totals">
      <div><dt>Subtotal</dt><dd><?= escapeHtml(formatGhs($confirmation['subtotal'])) ?></dd></div>
      <div><dt>Discount</dt><dd><?= escapeHtml(formatGhs($confirmation['discount_amount'])) ?></dd></div>
      <div><dt>Delivery</dt><dd><?= escapeHtml(formatGhs($confirmation['delivery_fee'])) ?></dd></div>
      <div><dt>Tax</dt><dd><?= escapeHtml(formatGhs($confirmation['tax_amount'])) ?></dd></div>
      <div><dt>Total due on delivery</dt><dd><?= escapeHtml(formatGhs($confirmation['total_amount'])) ?></dd></div>
    </dl>
    <div class="confirmation-actions">
      <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/orders.php">View my orders <span aria-hidden="true">→</span></a>
      <a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">Continue browsing</a>
    </div>
  </section>
</main>
