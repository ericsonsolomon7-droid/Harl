<?php
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\csrfToken;

$headerUser = $_SESSION['user'] ?? null;
$headerCartCount = max(0, (int) ($_SESSION['cart_item_count'] ?? 0));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Discover thoughtful reads, Ghanaian stories, and books for every curious mind.">
  <meta name="theme-color" content="#f6f3ed">
  <title><?= escapeHtml($pageTitle) ?> | The Reading Room</title>
  <link rel="stylesheet" href="<?= escapeHtml($basePath) ?>/assets/css/style.css">
  <script src="<?= escapeHtml($basePath) ?>/assets/js/app.js" defer></script>
</head>
<body id="top">
  <a class="skip-link" href="#main-content">Skip to content</a>
  <div class="announcement">Stories worth keeping. Delivered across Ghana.</div>
  <header class="site-header">
    <div class="header-inner">
      <a class="brand" href="<?= escapeHtml($basePath) ?>/" aria-label="The Reading Room home">
        <span class="brand-mark" aria-hidden="true">R</span>
        <span class="brand-name">The Reading Room<small>BOOKS &amp; MORE</small></span>
      </a>
      <form class="header-search" action="<?= escapeHtml($basePath) ?>/books.php" method="get" role="search">
        <label class="sr-only" for="site-search">Search books, authors, or ISBN</label>
        <input id="site-search" type="search" name="q" value="<?= escapeHtml($searchQuery) ?>" placeholder="Search books, authors, ISBN..." maxlength="120">
        <button type="submit" aria-label="Search">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 4.5 4.5"></path></svg>
        </button>
      </form>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
        <span class="sr-only">Toggle navigation</span>
        <span></span><span></span><span></span>
      </button>
      <nav class="primary-navigation" id="primary-navigation" aria-label="Main navigation">
        <a href="<?= escapeHtml($basePath) ?>/books.php">Books</a>
        <a href="<?= escapeHtml($basePath) ?>/about.php">About us</a>
        <a href="<?= escapeHtml($basePath) ?>/books.php#filters">Categories</a>
        <a href="<?= escapeHtml($basePath) ?>/#bestsellers">Best sellers</a>
      </nav>
      <div class="header-account">
        <?php if (is_array($headerUser) && ($headerUser['role'] ?? null) === 'admin'): ?>
          <a href="<?= escapeHtml($basePath) ?>/admin/">Admin</a>
        <?php elseif (is_array($headerUser) && ($headerUser['role'] ?? null) === 'seller'): ?>
          <a href="<?= escapeHtml($basePath) ?>/seller/">Seller dashboard</a>
        <?php else: ?>
          <a class="header-cart-link" href="<?= escapeHtml($basePath) ?>/cart.php">Cart <span class="cart-count"><?= $headerCartCount ?></span></a>
        <?php endif; ?>
        <?php if (is_array($headerUser) && isset($headerUser['first_name'])): ?>
          <span class="header-greeting">Hi, <?= escapeHtml($headerUser['first_name']) ?></span>
          <?php if (($headerUser['role'] ?? null) === 'customer'): ?>
            <a href="<?= escapeHtml($basePath) ?>/account.php">My account</a>
          <?php endif; ?>
          <form action="<?= escapeHtml($basePath) ?>/logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <button type="submit" class="logout-button">Sign out</button>
          </form>
        <?php else: ?>
          <a href="<?= escapeHtml($basePath) ?>/login.php">Sign in</a>
          <a class="header-join" href="<?= escapeHtml($basePath) ?>/register.php">Join</a>
          <a href="<?= escapeHtml($basePath) ?>/seller-register.php">Sell books</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
