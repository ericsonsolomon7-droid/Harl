<?php
use function Bookstore\Support\escapeHtml;
?>
<nav class="customer-nav" aria-label="Customer account">
  <a href="<?= escapeHtml($basePath) ?>/account.php"<?= $activeAccountPage === 'account' ? ' aria-current="page"' : '' ?>>Overview &amp; profile</a>
  <a href="<?= escapeHtml($basePath) ?>/orders.php"<?= $activeAccountPage === 'orders' ? ' aria-current="page"' : '' ?>>My orders</a>
  <a href="<?= escapeHtml($basePath) ?>/addresses.php"<?= $activeAccountPage === 'addresses' ? ' aria-current="page"' : '' ?>>Delivery addresses</a>
  <a href="<?= escapeHtml($basePath) ?>/wishlist.php"<?= $activeAccountPage === 'wishlist' ? ' aria-current="page"' : '' ?>>Wishlist</a>
</nav>
