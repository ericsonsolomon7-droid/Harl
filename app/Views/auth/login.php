<?php
use function Bookstore\Support\escapeHtml;
?>
<main id="main-content" class="auth-page">
  <section class="auth-panel">
    <span class="eyebrow">WELCOME BACK</span>
    <h1>Good to see you again.</h1>
    <p class="auth-intro">Sign in to pick up where your next good read begins.</p>
    <?php if ($errorMessage !== ''): ?>
      <div class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= escapeHtml($basePath) ?>/login.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= escapeHtml($email) ?>" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required>
      <button class="button button-dark" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch">New to the shop? <a href="<?= escapeHtml($basePath) ?>/register.php">Create an account</a></p>
  </section>
</main>
