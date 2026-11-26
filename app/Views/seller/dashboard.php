<?php use function Bookstore\Support\escapeHtml; ?>
<main id="main-content" class="customer-page seller-dashboard">
  <?php if ($errorMessage !== ''): ?>
    <section class="customer-panel"><p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p></section>
  <?php elseif ($seller !== null): ?>
    <header class="customer-heading">
      <span class="eyebrow">SELLER PORTAL</span>
      <h1><?= escapeHtml($seller['store_name']) ?></h1>
      <p>Manage your marketplace application and approved book listings.</p>
    </header>
    <section class="customer-panel seller-business-details">
      <div class="customer-section-heading"><div><span class="eyebrow">BUSINESS PROFILE</span><h2>Store details</h2></div></div>
      <dl>
        <div><dt>Business or store</dt><dd><?= escapeHtml($seller['store_name']) ?></dd></div>
        <div><dt>Business address</dt><dd><?= escapeHtml($seller['business_address']) ?></dd></div>
        <div><dt>Location</dt><dd><?= escapeHtml($seller['city'] . ', ' . $seller['region']) ?></dd></div>
        <div class="seller-description"><dt>About the business</dt><dd><?= nl2br(escapeHtml($seller['description'])) ?></dd></div>
      </dl>
    </section>
    <section class="customer-panel">
      <h2>Application status</h2>
      <p><strong><?= escapeHtml(ucwords(str_replace('_', ' ', (string) $seller['verification_status']))) ?></strong></p>
      <?php if ($seller['verification_status'] !== 'approved'): ?>
        <p>Your account cannot submit or publish books until the administrator approves your seller application.</p>
        <?php if (!empty($seller['rejection_reason'])): ?><p class="form-errors">Administrator feedback: <?= escapeHtml($seller['rejection_reason']) ?></p><?php endif; ?>
      <?php else: ?>
        <div class="customer-stat-grid">
          <div class="customer-stat"><span>Submitted books</span><strong><?= (int) ($dashboard['submitted_books'] ?? 0) ?></strong></div>
          <div class="customer-stat"><span>Approved books</span><strong><?= (int) ($dashboard['approved_books'] ?? 0) ?></strong></div>
          <div class="customer-stat"><span>Pending review</span><strong><?= (int) ($dashboard['pending_books'] ?? 0) ?></strong></div>
          <div class="customer-stat"><span>Available stock</span><strong><?= (int) ($dashboard['available_stock'] ?? 0) ?></strong></div>
          <div class="customer-stat"><span>Total orders</span><strong><?= (int) ($dashboard['total_orders'] ?? 0) ?></strong></div>
          <div class="customer-stat"><span>Estimated earnings</span><strong>GH₵ <?= escapeHtml(number_format((float) ($dashboard['estimated_earnings'] ?? 0), 2)) ?></strong></div>
        </div>
      <?php endif; ?>
    </section>
    <section class="customer-panel">
      <div class="customer-section-heading"><div><span class="eyebrow">NOTIFICATIONS</span><h2>Recent updates</h2></div><span><?= (int) ($dashboard['unread_notifications'] ?? 0) ?> unread</span></div>
      <?php if ($notifications === []): ?><p>No notifications yet.</p><?php else: ?>
        <?php foreach ($notifications as $notification): ?><article><h3><?= escapeHtml($notification['title']) ?></h3><p><?= escapeHtml($notification['message']) ?></p><small><?= escapeHtml(date('M j, Y g:i a', strtotime($notification['created_at']))) ?></small></article><?php endforeach; ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</main>
