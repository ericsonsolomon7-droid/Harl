<?php use function Bookstore\Support\escapeHtml; ?>
<main id="main-content" class="auth-page">
  <section class="auth-panel">
    <span class="eyebrow">JOIN THE MARKETPLACE</span>
    <h1>Open your book store.</h1>
    <p class="auth-intro">Apply to sell on The Reading Room. Every seller and book listing is reviewed by our administrator.</p>
    <?php if ($successMessage !== ''): ?><div class="form-notice form-notice-success" role="status"><?= escapeHtml($successMessage) ?><br><a href="<?= escapeHtml($basePath) ?>/login.php">Sign in after approval</a></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></div><?php endif; ?>
    <?php if ($successMessage === ''): ?>
      <form method="post" action="<?= escapeHtml($basePath) ?>/seller-register.php" class="auth-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <div class="auth-name-fields">
          <label for="first-name">First name<input id="first-name" name="first_name" maxlength="100" value="<?= escapeHtml($fields['first_name']) ?>" required></label>
          <label for="last-name">Last name<input id="last-name" name="last_name" maxlength="100" value="<?= escapeHtml($fields['last_name']) ?>" required></label>
        </div>
        <label for="seller-email">Email address<input id="seller-email" name="email" type="email" maxlength="254" value="<?= escapeHtml($fields['email']) ?>" required></label>
        <label for="seller-phone">Phone number<input id="seller-phone" name="phone" type="tel" maxlength="24" value="<?= escapeHtml($fields['phone']) ?>" placeholder="024 123 4567" required></label>
        <label for="store-name">Business or store name<input id="store-name" name="store_name" maxlength="180" value="<?= escapeHtml($fields['store_name']) ?>" required></label>
        <label for="seller-type">Seller type<select id="seller-type" name="seller_type" required><?php foreach ($sellerTypes as $value => $label): ?><option value="<?= escapeHtml($value) ?>" <?= $fields['seller_type'] === $value ? 'selected' : '' ?>><?= escapeHtml($label) ?></option><?php endforeach; ?></select></label>
        <label for="seller-region">Region<input id="seller-region" name="region" maxlength="100" value="<?= escapeHtml($fields['region']) ?>" required></label>
        <label for="seller-city">City or town<input id="seller-city" name="city" maxlength="120" value="<?= escapeHtml($fields['city']) ?>" required></label>
        <label for="business-address">Business address<textarea id="business-address" name="business_address" maxlength="255" required><?= escapeHtml($fields['business_address']) ?></textarea></label>
        <label for="description">Short business description<textarea id="description" name="description" maxlength="2000" required><?= escapeHtml($fields['description']) ?></textarea></label>
        <label for="business-registration-details">Business registration details <span class="field-optional">optional</span><textarea id="business-registration-details" name="business_registration_details" maxlength="500"><?= escapeHtml($fields['business_registration_details']) ?></textarea></label>
        <label for="business-logo">Profile image or business logo <span class="field-optional">optional, JPG/PNG/WebP, max 4 MB</span><input id="business-logo" name="business_logo" type="file" accept="image/jpeg,image/png,image/webp"></label>
        <label for="seller-password">Password<input id="seller-password" name="password" type="password" minlength="12" maxlength="72" required></label>
        <label for="seller-password-confirmation">Confirm password<input id="seller-password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" required></label>
        <button class="button button-dark" type="submit">Submit seller application <span aria-hidden="true">→</span></button>
      </form>
    <?php endif; ?>
    <p class="auth-switch">Already have an account? <a href="<?= escapeHtml($basePath) ?>/login.php">Sign in</a></p>
  </section>
</main>
