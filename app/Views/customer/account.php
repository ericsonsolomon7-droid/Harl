<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;

$activeAccountPage = 'account';
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">YOUR READING ROOM</span>
    <h1>Welcome, <?= escapeHtml($user['first_name']) ?>.</h1>
    <p>Manage your account details, delivery addresses, and reading list.</p>
  </header>
  <?php require __DIR__ . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
  <?php if ($flashMessage !== null): ?>
    <p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p>
  <?php endif; ?>
  <?php if ($errorMessage !== ''): ?>
    <p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p>
  <?php endif; ?>

  <section class="account-stats" aria-label="Account summary">
    <article><span>Orders placed</span><strong><?= (int) $summary['orders'] ?></strong></article>
    <article><span>Wishlist books</span><strong><?= (int) $summary['wishlist_items'] ?></strong></article>
    <article><span>Saved addresses</span><strong><?= (int) $summary['addresses'] ?></strong></article>
  </section>

  <section class="customer-panel profile-panel">
    <div class="profile-heading">
      <div class="profile-avatar" aria-hidden="true"><?= escapeHtml(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?></div>
      <div class="customer-section-heading">
        <div><span class="eyebrow">ACCOUNT DETAILS</span><h2>Personal information</h2></div>
        <p>Keep your contact details current.</p>
      </div>
    </div>
    <form method="post" action="<?= escapeHtml($basePath) ?>/account.php" class="customer-form">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <input type="hidden" name="action" value="profile">
      <div class="customer-form-grid">
        <label>First name
          <input name="first_name" type="text" autocomplete="given-name" maxlength="100" value="<?= escapeHtml($user['first_name']) ?>" required>
          <span class="field-hint">The name used on your account</span>
        </label>
        <label>Last name
          <input name="last_name" type="text" autocomplete="family-name" maxlength="100" value="<?= escapeHtml($user['last_name']) ?>" required>
          <span class="field-hint">Your family or surname</span>
        </label>
        <label>Email address
          <input name="email" type="email" autocomplete="email" maxlength="254" value="<?= escapeHtml($user['email']) ?>" required>
          <span class="field-hint">Used for sign-in and account updates</span>
        </label>
        <label>Ghana phone number
          <input name="phone" type="tel" autocomplete="tel" maxlength="24" placeholder="024 123 4567" value="<?= escapeHtml($user['phone'] ?? '') ?>">
          <span class="field-hint">Optional; used for order and delivery contact</span>
        </label>
      </div>
      <button class="button button-dark" type="submit">Save profile <span aria-hidden="true">→</span></button>
    </form>
  </section>

  <section class="customer-panel">
    <div class="customer-section-heading">
      <div><span class="eyebrow">ACCOUNT SECURITY</span><h2>Change password</h2></div>
    </div>
    <form method="post" action="<?= escapeHtml($basePath) ?>/account.php" class="customer-form">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <input type="hidden" name="action" value="password">
      <div class="customer-form-grid">
        <label>Current password
          <input name="current_password" type="password" autocomplete="current-password" maxlength="72" required>
        </label>
        <label>New password <span class="field-optional">At least 12 characters; maximum 72 bytes</span>
          <input name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <label>Confirm new password
          <input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
      </div>
      <button class="button button-dark" type="submit">Update password <span aria-hidden="true">→</span></button>
    </form>
  </section>

  <section class="customer-panel">
    <div class="customer-section-heading">
      <div><span class="eyebrow">RECENT ACTIVITY</span><h2>Recent orders</h2></div>
      <a class="text-link" href="<?= escapeHtml($basePath) ?>/orders.php">View all orders <span aria-hidden="true">→</span></a>
    </div>
    <?php if ($recentOrders === []): ?>
      <div class="customer-empty"><p>You have not placed an order yet.</p><a class="text-link" href="<?= escapeHtml($basePath) ?>/books.php">Explore the catalogue <span aria-hidden="true">→</span></a></div>
    <?php else: ?>
      <div class="orders-table-wrap">
        <table class="orders-table">
          <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Status</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td><?= escapeHtml($order['order_number']) ?></td>
                <td><time datetime="<?= escapeHtml($order['placed_at']) ?>"><?= escapeHtml(date('M j, Y', strtotime($order['placed_at']))) ?></time></td>
                <td><?= (int) $order['item_quantity'] ?></td>
                <td><span class="status-pill"><?= escapeHtml(ucfirst($order['order_status'])) ?></span></td>
                <td><?= escapeHtml(formatGhs($order['total_amount'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
