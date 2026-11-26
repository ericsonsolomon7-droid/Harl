<?php
$pageTitle = 'Page not found';
$searchQuery = '';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>
<main id="main-content" class="not-found-page">
  <span class="eyebrow">A PAGE OUT OF PLACE</span>
  <h1>We can’t find that book.</h1>
  <p>It may have moved from the shelf, or the link may be incorrect.</p>
  <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/books.php">Browse all books <span aria-hidden="true">→</span></a>
</main>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
