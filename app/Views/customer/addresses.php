<?php
use function Bookstore\Support\escapeHtml;

$activeAccountPage = 'addresses';
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">DELIVERY DETAILS</span>
    <h1>Your addresses</h1>
    <p>Keep Ghana delivery details ready for your next order.</p>
  </header>
  <?php require __DIR__ . DIRECTORY_SEPARATOR . 'navigation.php'; ?>
  <?php if ($flashMessage !== null): ?>
    <p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p>
  <?php endif; ?>
  <?php if ($errorMessage !== ''): ?>
    <p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p>
  <?php endif; ?>

  <section class="customer-panel">
    <div class="customer-section-heading">
      <div><span class="eyebrow"><?= $editAddressId === null ? 'NEW ADDRESS' : 'EDIT ADDRESS' ?></span><h2><?= $editAddressId === null ? 'Add a delivery address' : 'Update delivery address' ?></h2></div>
      <?php if ($editAddressId !== null): ?><a class="text-link" href="<?= escapeHtml($basePath) ?>/addresses.php">Cancel edit</a><?php endif; ?>
    </div>
    <form method="post" action="<?= escapeHtml($basePath) ?>/addresses.php" class="customer-form">
      <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editAddressId !== null): ?><input type="hidden" name="address_id" value="<?= (int) $editAddressId ?>"><?php endif; ?>
      <div class="customer-form-grid">
        <label>Recipient's full name
          <input name="recipient_name" type="text" autocomplete="name" maxlength="200" value="<?= escapeHtml($formData['recipient_name']) ?>" required>
        </label>
        <label>Phone number
          <input name="phone" type="tel" autocomplete="tel" maxlength="30" placeholder="024 123 4567" value="<?= escapeHtml($formData['phone']) ?>" required>
        </label>
        <label>Region
          <input name="region" type="text" autocomplete="address-level1" maxlength="100" value="<?= escapeHtml($formData['region']) ?>" required>
        </label>
        <label>City or town
          <input name="city" type="text" autocomplete="address-level2" maxlength="120" value="<?= escapeHtml($formData['city']) ?>" required>
        </label>
        <label class="customer-form-wide">Residential address
          <input name="address_line" type="text" autocomplete="street-address" maxlength="255" value="<?= escapeHtml($formData['address_line']) ?>" required>
        </label>
        <label>Landmark <span class="field-optional">Optional</span>
          <input name="landmark" type="text" maxlength="255" value="<?= escapeHtml($formData['landmark'] ?? '') ?>">
        </label>
        <label class="customer-form-wide">Delivery instructions <span class="field-optional">Optional</span>
          <textarea name="delivery_instructions" rows="3" maxlength="1000"><?= escapeHtml($formData['delivery_instructions'] ?? '') ?></textarea>
        </label>
      </div>
      <label class="checkbox-label"><input type="checkbox" name="is_default" value="1" <?= (int) $formData['is_default'] === 1 ? 'checked' : '' ?>> Set as my default delivery address</label>
      <button class="button button-dark" type="submit">Save address <span aria-hidden="true">→</span></button>
    </form>
  </section>

  <section class="customer-panel">
    <div class="customer-section-heading">
      <div><span class="eyebrow">SAVED LOCATIONS</span><h2>Address book</h2></div>
    </div>
    <?php if ($addresses === []): ?>
      <div class="customer-empty"><p>No delivery addresses saved yet.</p></div>
    <?php else: ?>
      <div class="address-list">
        <?php foreach ($addresses as $address): ?>
          <article class="address-card">
            <div class="address-card-heading">
              <h3><?= escapeHtml($address['recipient_name']) ?></h3>
              <?php if ((int) $address['is_default'] === 1): ?><span class="status-pill">Default</span><?php endif; ?>
            </div>
            <p><?= escapeHtml($address['address_line']) ?><?= $address['landmark'] ? ', ' . escapeHtml($address['landmark']) : '' ?><br><?= escapeHtml($address['city']) ?>, <?= escapeHtml($address['region']) ?></p>
            <p><?= escapeHtml($address['phone']) ?></p>
            <?php if ($address['delivery_instructions']): ?><p class="address-instructions"><?= escapeHtml($address['delivery_instructions']) ?></p><?php endif; ?>
            <div class="address-actions">
              <a class="text-link" href="<?= escapeHtml($basePath) ?>/addresses.php?edit=<?= (int) $address['id'] ?>">Edit</a>
              <?php if ((int) $address['is_default'] !== 1): ?>
                <form method="post" action="<?= escapeHtml($basePath) ?>/addresses.php">
                  <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                  <input type="hidden" name="action" value="default">
                  <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                  <button class="text-button" type="submit">Set as default</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= escapeHtml($basePath) ?>/addresses.php">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                <button class="text-button text-button-danger" type="submit">Remove</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
