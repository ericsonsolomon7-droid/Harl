<?php
use function Bookstore\Support\escapeHtml;
?>
<main id="main-content" class="auth-page">
  <section class="auth-panel">
    <span class="eyebrow">JOIN OUR READING COMMUNITY</span>
    <h1>Your next story starts here.</h1>
    <p class="auth-intro">Create an account to keep your reading life in one place.</p>
    <?php if ($errorMessage !== ''): ?>
      <div class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= escapeHtml($basePath) ?>/register.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <div class="auth-name-fields">
        <label for="first-name">First name
          <input id="first-name" name="first_name" type="text" autocomplete="given-name" maxlength="100" value="<?= escapeHtml($firstName) ?>" required>
        </label>
        <label for="last-name">Last name
          <input id="last-name" name="last_name" type="text" autocomplete="family-name" maxlength="100" value="<?= escapeHtml($lastName) ?>" required>
        </label>
      </div>
      <label for="email">Email address</label>
      <input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= escapeHtml($email) ?>" required>
      <label for="phone">Ghana phone number <span class="field-optional">optional</span></label>
      <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="24" value="<?= escapeHtml($phone) ?>" placeholder="024 123 4567">
      <label for="password">Password <span class="field-optional">12–72 characters; maximum 72 bytes</span></label>
      <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
      <label for="password-confirmation">Confirm password</label>
      <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
      <button class="button button-dark" type="submit">Create account <span aria-hidden="true">→</span></button>
    </form>
    <p class="auth-switch">Already have an account? <a href="<?= escapeHtml($basePath) ?>/login.php">Sign in</a></p>
  </section>
</main>
