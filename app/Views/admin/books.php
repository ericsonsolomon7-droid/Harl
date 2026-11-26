<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<header class="admin-page-heading">
  <div><span class="admin-eyebrow">CATALOGUE MANAGEMENT</span><h1>Books</h1><p>Add, edit, archive, or delete direct catalogue listings.</p></div>
  <a class="admin-button" href="<?= escapeHtml($basePath) ?>/books.php">Add book</a>
</header>

<?php if ($flashMessage !== null): ?><p class="admin-notice" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
<?php if ($errorMessage !== ''): ?><p class="admin-error" role="alert"><?= escapeHtml($errorMessage) ?></p><?php endif; ?>

<section class="admin-panel">
  <div class="admin-section-heading"><div><span class="admin-eyebrow">BOOK DETAILS</span><h2><?= $editBook === null ? 'Add a book' : 'Edit ' . escapeHtml($editBook['title']) ?></h2></div></div>
  <form method="post" action="<?= escapeHtml($basePath) ?>/books.php" class="admin-book-form">
    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
    <input type="hidden" name="action" value="save">
    <?php if ($editBook !== null): ?><input type="hidden" name="book_id" value="<?= (int) $editBook['id'] ?>"><?php endif; ?>
    <div class="admin-form-grid">
      <label>Title<input name="title" maxlength="255" required value="<?= escapeHtml($editBook['title'] ?? '') ?>"></label>
      <label>Author<input name="author_text" maxlength="255" required value="<?= escapeHtml($editBook['author_text'] ?? '') ?>"></label>
      <label>ISBN<input name="isbn" maxlength="20" required value="<?= escapeHtml($editBook['isbn'] ?? '') ?>"></label>
      <label>URL slug<input name="slug" maxlength="280" required value="<?= escapeHtml($editBook['slug'] ?? '') ?>" placeholder="book-title"></label>
      <label>Category<select name="category_id" required><option value="">Choose category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editBook['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= escapeHtml($category['name']) ?></option><?php endforeach; ?></select></label>
      <label>Status<select name="status"><option value="draft" <?= ($editBook['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="active" <?= ($editBook['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="archived" <?= ($editBook['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option></select></label>
      <label>Regular price (GH₵)<input name="price" type="number" min="0" step="0.01" required value="<?= escapeHtml($editBook['price'] ?? '') ?>"></label>
      <label>Discount price (optional)<input name="discount_price" type="number" min="0" step="0.01" value="<?= escapeHtml($editBook['discount_price'] ?? '') ?>"></label>
      <label>Stock quantity<input name="stock_quantity" type="number" min="0" step="1" required value="<?= escapeHtml($editBook['stock_quantity'] ?? '0') ?>"></label>
      <label>Low-stock alert<input name="low_stock_threshold" type="number" min="0" step="1" required value="<?= escapeHtml($editBook['low_stock_threshold'] ?? '5') ?>"></label>
      <label>Publication date<input name="publication_date" type="date" value="<?= escapeHtml($editBook['publication_date'] ?? '') ?>"></label>
      <label>Language<input name="language" maxlength="60" value="<?= escapeHtml($editBook['language'] ?? 'English') ?>"></label>
      <label>Page count<input name="page_count" type="number" min="1" step="1" value="<?= escapeHtml($editBook['page_count'] ?? '') ?>"></label>
      <label>Cover image path<input name="cover_image" maxlength="500" value="<?= escapeHtml($editBook['cover_image'] ?? '') ?>" placeholder="assets/covers/book.jpg"></label>
      <label class="admin-form-wide">Description<textarea name="description" required maxlength="5000"><?= escapeHtml($editBook['description'] ?? '') ?></textarea></label>
    </div>
    <button class="admin-button" type="submit"><?= $editBook === null ? 'Add book' : 'Save changes' ?></button>
    <?php if ($editBook !== null): ?><a class="admin-button admin-button-secondary" href="<?= escapeHtml($basePath) ?>/books.php">Cancel edit</a><?php endif; ?>
  </form>
</section>

<section class="admin-panel">
  <form method="get" action="<?= escapeHtml($basePath) ?>/books.php" class="admin-filters">
    <label>Search books<input type="search" name="q" maxlength="100" value="<?= escapeHtml($search) ?>" placeholder="Title, author, or ISBN"></label>
    <button class="admin-button" type="submit">Search</button>
  </form>
  <?php if ($books === []): ?>
    <p class="admin-empty">No direct catalogue books match these filters.</p>
  <?php else: ?>
    <div class="admin-table-scroll"><table class="admin-table"><thead><tr><th>Book</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($books as $book): ?><tr>
        <td><strong><?= escapeHtml($book['title']) ?></strong><br><small><?= escapeHtml($book['author_text'] ?: 'Author not set') ?> · <?= escapeHtml($book['isbn']) ?></small></td>
        <td><?= escapeHtml($book['category_name'] ?: 'Uncategorised') ?></td>
        <td><?= escapeHtml(formatGhs($book['discount_price'] ?? $book['price'])) ?></td>
        <td><?= (int) $book['stock_quantity'] ?></td>
        <td><span class="admin-status"><?= escapeHtml(ucfirst($book['status'])) ?></span></td>
        <td><div class="admin-marketplace-actions">
          <a class="admin-button admin-button-secondary" href="<?= escapeHtml($basePath) ?>/books.php?edit=<?= (int) $book['id'] ?>">Edit</a>
          <form method="post" action="<?= escapeHtml($basePath) ?>/books.php" onsubmit="return confirm('Delete this catalogue book? Books with existing orders will be archived.');">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
            <button class="admin-button admin-button-danger" type="submit">Delete</button>
          </form>
        </div></td>
      </tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <div class="admin-pagination"><span>Page <?= $page ?> of <?= $totalPages ?></span><?php if ($page > 1): ?><a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query(['q' => $search, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?><?php if ($page < $totalPages): ?><a href="<?= escapeHtml($basePath) ?>/books.php?<?= escapeHtml(http_build_query(['q' => $search, 'page' => $page + 1])) ?>">Next</a><?php endif; ?></div>
</section>
