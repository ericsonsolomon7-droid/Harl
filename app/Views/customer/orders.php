<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;

$activeAccountPage = 'orders';
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">YOUR PURCHASES</span>
    <h1>Order history</h1>
    <p>Review your recent purchases and current order statuses.</p>
  </header>
  <?php require __DIR__ . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
  <?php if ($flashMessage !== null): ?>
    <p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p>
  <?php endif; ?>
  <section class="customer-panel">
    <div class="customer-section-heading">
      <div><span class="eyebrow">PURCHASE HISTORY</span><h2><?= number_format($totalOrders) ?> <?= $totalOrders === 1 ? 'order' : 'orders' ?></h2></div>
    </div>
    <?php if ($orders === []): ?>
      <div class="customer-empty"><p>Your order history is empty.</p><a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">Find your next read <span aria-hidden="true">→</span></a></div>
    <?php else: ?>
      <div class="orders-table-wrap">
        <table class="orders-table">
          <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Order status</th><th>Payment</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td><?= escapeHtml($order['order_number']) ?></td>
                <td><time datetime="<?= escapeHtml($order['placed_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($order['placed_at']))) ?></time></td>
                <td><?= (int) $order['item_quantity'] ?></td>
                <td><span class="status-pill"><?= escapeHtml(ucfirst($order['order_status'])) ?></span></td>
                <td><?= escapeHtml(ucfirst($order['payment_status'])) ?></td>
                <td><?= escapeHtml(formatGhs($order['total_amount'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav class="account-pagination" aria-label="Order history pages">
          <?php if ($page > 1): ?><a href="<?= escapeHtml($basePath) ?>/orders.php?page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
          <span>Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?><a href="<?= escapeHtml($basePath) ?>/orders.php?page=<?= $page + 1 ?>">Next</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</main>
