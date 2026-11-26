<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<header class="admin-page-heading">
  <div><span class="admin-eyebrow">STORE OPERATIONS</span><h1>Order management</h1><p><?= number_format($totalOrders) ?> matching <?= $totalOrders === 1 ? 'order' : 'orders' ?>.</p></div>
</header>
<?php if ($flashMessage !== null): ?><p class="admin-notice" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
<?php if ($errorMessage !== ''): ?><p class="admin-error" role="alert"><?= escapeHtml($errorMessage) ?></p><?php endif; ?>

<section class="admin-panel">
  <form class="admin-filters" method="get" action="<?= escapeHtml($basePath) ?>/orders.php">
    <label>Order status
      <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $orderStatus): ?>
          <option value="<?= escapeHtml($orderStatus) ?>" <?= $status === $orderStatus ? 'selected' : '' ?>><?= escapeHtml(ucfirst($orderStatus)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Search order or customer
      <input type="search" name="q" maxlength="100" value="<?= escapeHtml($search) ?>" placeholder="Order number, name, or email">
    </label>
    <button class="admin-button" type="submit">Filter</button>
  </form>
</section>

<?php if ($orders === []): ?>
  <section class="admin-panel"><p class="admin-empty">No orders match these filters.</p></section>
<?php else: ?>
  <section class="admin-order-list" aria-label="Orders">
    <?php foreach ($orders as $order): ?>
      <article class="admin-order-card">
        <header class="admin-order-heading">
          <div><span class="admin-eyebrow">ORDER</span><h2><?= escapeHtml($order['order_number']) ?></h2><p><?= escapeHtml(date('M j, Y · g:i a', strtotime($order['placed_at']))) ?></p></div>
          <strong><?= escapeHtml(formatGhs($order['total_amount'])) ?></strong>
        </header>
        <div class="admin-order-details">
          <div><span>Customer</span><strong><?= escapeHtml($order['recipient_name']) ?></strong><a href="mailto:<?= escapeHtml($order['recipient_email']) ?>"><?= escapeHtml($order['recipient_email']) ?></a><span><?= escapeHtml($order['recipient_phone']) ?></span></div>
          <div><span>Delivery address</span><strong><?= escapeHtml($order['delivery_city']) ?>, <?= escapeHtml($order['delivery_region']) ?></strong><span><?= escapeHtml($order['delivery_address']) ?></span></div>
          <div><span>Order status</span><strong class="admin-status"><?= escapeHtml(ucfirst($order['order_status'])) ?></strong></div>
          <div><span>Payment</span><strong><?= escapeHtml(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?> · <?= escapeHtml(ucfirst($order['payment_status'])) ?></strong></div>
        </div>
        <footer class="admin-order-actions">
          <?php $nextStatuses = $transitions[$order['order_status']] ?? []; ?>
          <?php if ($nextStatuses !== []): ?>
            <form method="post" action="<?= escapeHtml($basePath) ?>/orders.php" class="admin-action-form">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <label class="sr-only" for="status-<?= (int) $order['id'] ?>">New status for order <?= escapeHtml($order['order_number']) ?></label>
              <select id="status-<?= (int) $order['id'] ?>" name="order_status" required>
                <?php foreach ($nextStatuses as $nextStatus): ?><option value="<?= escapeHtml($nextStatus) ?>"><?= escapeHtml(ucfirst($nextStatus)) ?></option><?php endforeach; ?>
              </select>
              <button class="admin-button" type="submit">Update status</button>
            </form>
          <?php endif; ?>
          <?php if ($order['payment_method'] === 'cash_on_delivery' && $order['payment_status'] === 'unpaid' && $order['order_status'] !== 'cancelled'): ?>
            <form method="post" action="<?= escapeHtml($basePath) ?>/orders.php">
              <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
              <input type="hidden" name="action" value="collect_cod">
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <button class="admin-button admin-button-secondary" type="submit">Record COD collected</button>
            </form>
          <?php endif; ?>
        </footer>
      </article>
    <?php endforeach; ?>
  </section>
  <?php if ($totalPages > 1): ?>
    <nav class="admin-pagination" aria-label="Order pages">
      <?php if ($page > 1): ?><a href="<?= escapeHtml($basePath) ?>/orders.php?<?= escapeHtml(http_build_query(array_filter(['status' => $status, 'q' => $search, 'page' => $page - 1], static fn (mixed $value): bool => $value !== null && $value !== ''))) ?>">Previous</a><?php endif; ?>
      <span>Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?><a href="<?= escapeHtml($basePath) ?>/orders.php?<?= escapeHtml(http_build_query(array_filter(['status' => $status, 'q' => $search, 'page' => $page + 1], static fn (mixed $value): bool => $value !== null && $value !== ''))) ?>">Next</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
