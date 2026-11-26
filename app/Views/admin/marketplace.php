<?php use function Bookstore\Support\escapeHtml; ?>
<header class="admin-page-heading">
  <div><span class="admin-eyebrow">MARKETPLACE MANAGEMENT</span><h1>Seller and book approvals</h1><p><?= number_format($totalItems) ?> matching <?= $section === 'sellers' ? 'seller applications' : 'book submissions' ?>.</p></div>
  <a class="admin-button" href="<?= escapeHtml($basePath) ?>/marketplace.php?section=<?= $section === 'sellers' ? 'books' : 'sellers' ?>">View <?= $section === 'sellers' ? 'book approvals' : 'seller applications' ?></a>
</header>
<?php if ($flashMessage !== null): ?><p class="admin-notice" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
<?php if ($errorMessage !== ''): ?><p class="admin-error" role="alert"><?= escapeHtml($errorMessage) ?></p><?php endif; ?>

<section class="admin-panel">
  <form class="admin-filters" method="get" action="<?= escapeHtml($basePath) ?>/marketplace.php">
    <input type="hidden" name="section" value="<?= escapeHtml($section) ?>">
    <label>Status<select name="status"><option value="">All statuses</option><?php foreach (($section === 'sellers' ? $sellerStatuses : $bookStatuses) as $option): ?><option value="<?= escapeHtml($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= escapeHtml(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select></label>
    <label>Search<input type="search" name="q" maxlength="100" value="<?= escapeHtml($search) ?>" placeholder="<?= $section === 'sellers' ? 'Store, seller, or email' : 'Title, seller, or ISBN' ?>"></label>
    <button class="admin-button" type="submit">Filter</button>
  </form>
</section>

<?php if ($items === []): ?>
  <section class="admin-panel"><p class="admin-empty">No records match these filters.</p></section>
<?php elseif ($section === 'sellers'): ?>
  <section class="admin-panel"><div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>Seller</th><th>Contact</th><th>Type/location</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($items as $item): ?><tr>
    <td><strong><?= escapeHtml($item['store_name']) ?></strong><br><?= escapeHtml($item['first_name'] . ' ' . $item['last_name']) ?></td>
    <td><?= escapeHtml($item['email']) ?><br><?= escapeHtml((string) $item['phone']) ?></td>
    <td><?= escapeHtml(ucwords(str_replace('_', ' ', $item['seller_type']))) ?><br><?= escapeHtml($item['city'] . ', ' . $item['region']) ?></td>
    <td><?= escapeHtml(date('M j, Y', strtotime($item['submitted_at'] ?? $item['created_at']))) ?></td>
    <td><span class="admin-status"><?= escapeHtml(ucwords(str_replace('_', ' ', $item['verification_status']))) ?></span></td>
    <td><div class="admin-marketplace-actions">
      <?php foreach (['approve_seller' => 'Approve', 'review_seller' => 'Under review', 'reject_seller' => 'Reject', 'suspend_seller' => 'Suspend'] as $action => $label): ?><form method="post" action="<?= escapeHtml($basePath) ?>/marketplace.php?section=sellers" onsubmit="return confirm('Confirm this seller action?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="seller_id" value="<?= (int) $item['id'] ?>"><?php if (in_array($action, ['reject_seller', 'suspend_seller'], true)): ?><input name="reason" required maxlength="2000" placeholder="Reason"><?php endif; ?><button class="admin-button <?= in_array($action, ['reject_seller', 'suspend_seller'], true) ? 'admin-button-danger' : '' ?>" type="submit"><?= $label ?></button></form><?php endforeach; ?>
    </div></td>
  </tr><?php endforeach; ?></tbody></table></div></section>
<?php else: ?>
  <section class="admin-panel"><div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>Book</th><th>Seller</th><th>Category</th><th>Price/stock</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($items as $item): ?><tr>
    <td><strong><?= escapeHtml($item['title']) ?></strong><br><?= escapeHtml($item['isbn']) ?></td>
    <td><?= escapeHtml($item['store_name']) ?><br><small><?= escapeHtml(ucwords(str_replace('_', ' ', $item['verification_status']))) ?></small></td>
    <td><?= escapeHtml((string) ($item['category_name'] ?? 'Uncategorized')) ?></td>
    <td>GH₵ <?= escapeHtml(number_format((float) ($item['discount_price'] ?? $item['price']), 2)) ?><br><?= (int) $item['stock_quantity'] ?> available</td>
    <td><?= escapeHtml(date('M j, Y', strtotime($item['submitted_at'] ?? $item['created_at']))) ?></td>
    <td><span class="admin-status"><?= escapeHtml(ucwords(str_replace('_', ' ', $item['status']))) ?></span></td>
    <td><div class="admin-marketplace-actions">
      <?php foreach (['approve_book' => 'Approve', 'reject_book' => 'Reject', 'revision_book' => 'Revision', 'suspend_book' => 'Suspend'] as $action => $label): ?><form method="post" action="<?= escapeHtml($basePath) ?>/marketplace.php?section=books" onsubmit="return confirm('Confirm this book action?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="book_id" value="<?= (int) $item['id'] ?>"><?php if ($action !== 'approve_book'): ?><input name="reason" required maxlength="2000" placeholder="Reason"><?php endif; ?><button class="admin-button <?= $action !== 'approve_book' ? 'admin-button-danger' : '' ?>" type="submit"><?= $label ?></button></form><?php endforeach; ?>
    </div></td>
  </tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>

<?php if ($totalPages > 1): ?><nav class="admin-pagination" aria-label="Marketplace pages"><?php if ($page > 1): ?><a href="<?= escapeHtml($basePath) ?>/marketplace.php?<?= escapeHtml(http_build_query(['section' => $section, 'status' => $status, 'q' => $search, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page < $totalPages): ?><a href="<?= escapeHtml($basePath) ?>/marketplace.php?<?= escapeHtml(http_build_query(['section' => $section, 'status' => $status, 'q' => $search, 'page' => $page + 1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
