<?php
use function Bookstore\Support\csrfToken;
use function Bookstore\Support\escapeHtml;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= escapeHtml($pageTitle) ?> | Reading Room Admin</title>
  <link rel="stylesheet" href="<?= escapeHtml(dirname($basePath)) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
  <a class="admin-skip-link" href="#admin-main">Skip to admin content</a>
  <header class="admin-header">
    <a class="admin-brand" href="<?= escapeHtml($basePath) ?>/index.php"><span>R</span><strong>Reading Room<small>ADMINISTRATION</small></strong></a>
    <nav aria-label="Administration">
      <a href="<?= escapeHtml($basePath) ?>/index.php">Overview</a>
      <a href="<?= escapeHtml($basePath) ?>/orders.php">Orders</a>
      <a href="<?= escapeHtml($basePath) ?>/books.php">Books</a>
      <a href="<?= escapeHtml($basePath) ?>/marketplace.php">Marketplace</a>
      <a href="<?= escapeHtml(dirname($basePath)) ?>/">Storefront</a>
    </nav>
    <div class="admin-user">
      <span><?= escapeHtml($_SESSION['user']['first_name'] ?? 'Administrator') ?></span>
      <form action="<?= escapeHtml(dirname($basePath)) ?>/logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <button type="submit">Sign out</button>
      </form>
    </div>
  </header>
  <main id="admin-main" class="admin-main">
