<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<header class="admin-page-heading">
  <div><span class="admin-eyebrow">STORE OPERATIONS</span><h1>Overview</h1><p>Live totals from bookstore records.</p></div>
  <a class="admin-button" href="<?= escapeHtml($basePath) ?>/orders.php">Manage orders <span aria-hidden="true">→</span></a>
</header>

<section class="admin-metrics" aria-label="Store statistics">
  <article><span>Recorded revenue</span><strong><?= escapeHtml(formatGhs($dashboard['revenue'])) ?></strong><small>Paid orders, excluding cancellations</small></article>
  <article><span>Total orders</span><strong><?= (int) $dashboard['summary']['orders'] ?></strong><small>All order statuses</small></article>
  <article><span>Customers</span><strong><?= (int) $dashboard['summary']['customers'] ?></strong><small>Registered customer accounts</small></article>
  <article><span>Books listed</span><strong><?= (int) $dashboard['summary']['books'] ?></strong><small>All catalogue statuses</small></article>
  <article><span>Orders to process</span><strong><?= (int) $dashboard['summary']['pending_orders'] ?></strong><small>Pending, confirmed, or processing</small></article>
  <article><span>Delivered</span><strong><?= (int) $dashboard['summary']['completed_orders'] ?></strong><small>Completed deliveries</small></article>
  <article><span>Low stock</span><strong><?= (int) $dashboard['summary']['low_stock'] ?></strong><small>At or below configured thresholds</small></article>
</section>

<section class="admin-panel">
  <div class="admin-section-heading"><div><span class="admin-eyebrow">LATEST ACTIVITY</span><h2>Recent orders</h2></div><a href="<?= escapeHtml($basePath) ?>/orders.php">All orders →</a></div>
  <?php if ($dashboard['recent_orders'] === []): ?>
    <p class="admin-empty">No orders have been placed yet.</p>
  <?php else: ?>
    <div class="admin-table-scroll">
      <table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Order status</th><th>Payment</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($dashboard['recent_orders'] as $order): ?>
        <tr><td><?= escapeHtml($order['order_number']) ?></td><td><?= escapeHtml($order['recipient_name']) ?></td><td><?= escapeHtml(date('M j, Y', strtotime($order['placed_at']))) ?></td><td><span class="admin-status"><?= escapeHtml(ucfirst($order['order_status'])) ?></span></td><td><?= escapeHtml(ucfirst($order['payment_status'])) ?></td><td><?= escapeHtml(formatGhs($order['total_amount'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  <?php endif; ?>
</section>

<section class="admin-panel">
  <div class="admin-section-heading"><div><span class="admin-eyebrow">INVENTORY WATCH</span><h2>Low-stock books</h2></div></div>
  <?php if ($dashboard['low_stock_books'] === []): ?>
    <p class="admin-empty">No active books are currently below their low-stock threshold.</p>
  <?php else: ?>
    <div class="admin-table-scroll">
      <table class="admin-table"><thead><tr><th>Book</th><th>ISBN</th><th>Available</th><th>Alert threshold</th></tr></thead><tbody>
      <?php foreach ($dashboard['low_stock_books'] as $book): ?>
        <tr><td><?= escapeHtml($book['title']) ?></td><td><?= escapeHtml($book['isbn']) ?></td><td class="admin-low-stock"><?= (int) $book['stock_quantity'] ?></td><td><?= (int) $book['low_stock_threshold'] ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  <?php endif; ?>
</section>
