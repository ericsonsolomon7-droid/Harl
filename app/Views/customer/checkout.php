<?php
use Bookstore\Services\CustomerCart;
use function Bookstore\Support\escapeHtml;
use function Bookstore\Support\formatGhs;
?>
<main id="main-content" class="customer-page">
  <header class="customer-heading">
    <span class="eyebrow">SECURE CHECKOUT</span>
    <h1>Delivery &amp; payment</h1>
    <p>Review your delivery details and order total before placing your order.</p>
  </header>
  <?php if ($flashMessage !== null): ?><p class="form-notice form-notice-<?= escapeHtml($flashMessage['type']) ?>" role="status"><?= escapeHtml($flashMessage['message']) ?></p><?php endif; ?>
  <?php if ($errorMessage !== ''): ?><p class="form-errors" role="alert"><?= escapeHtml($errorMessage) ?></p><?php endif; ?>

  <?php if ($cartData['items'] === []): ?>
    <section class="customer-panel customer-empty">
      <h2>Your cart is empty.</h2>
      <p>Add books before starting checkout.</p>
      <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/books.php">Browse books <span aria-hidden="true">→</span></a>
    </section>
  <?php elseif ($addresses === []): ?>
    <section class="customer-panel customer-empty">
      <h2>Add a delivery address to continue.</h2>
      <p>Checkout uses one of your saved Ghana delivery addresses.</p>
      <a class="button button-dark" href="<?= escapeHtml($basePath) ?>/addresses.php">Add delivery address <span aria-hidden="true">→</span></a>
    </section>
  <?php else: ?>
    <form method="post" action="<?= escapeHtml($basePath) ?>/checkout.php" class="checkout-layout">
      <section class="customer-panel checkout-details">
        <div class="customer-section-heading"><div><span class="eyebrow">STEP 1</span><h2>Delivery address</h2></div></div>
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
        <label class="checkout-address-label" for="checkout-address">Deliver to</label>
        <select id="checkout-address" name="address_id" required>
          <?php foreach ($addresses as $address): ?>
            <option value="<?= (int) $address['id'] ?>" <?= (int) $address['id'] === $selectedAddressId ? 'selected' : '' ?>>
              <?= escapeHtml($address['recipient_name'] . ' — ' . $address['address_line'] . ', ' . $address['city'] . ', ' . $address['region']) ?><?= (int) $address['is_default'] === 1 ? ' (default)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <a class="text-link" href="<?= escapeHtml($basePath) ?>/addresses.php">Manage delivery addresses</a>

        <div class="customer-section-heading checkout-payment-heading"><div><span class="eyebrow">STEP 2</span><h2>Payment method</h2></div></div>
        <label class="payment-choice">
          <input type="radio" name="payment_method" value="cash_on_delivery" checked>
          <span><strong>Cash on Delivery</strong><small>Pay when your order arrives. Online payment methods are not enabled.</small></span>
        </label>

        <label class="checkout-address-label" for="coupon-code">Discount coupon <span class="field-optional">Optional</span></label>
        <div class="coupon-entry">
          <input id="coupon-code" name="coupon_code" type="text" maxlength="50" pattern="[A-Za-z0-9_-]{1,50}" value="<?= escapeHtml($couponCode) ?>" autocomplete="off">
          <button class="button button-outline" type="submit" name="action" value="review">Apply / update total</button>
        </div>
      </section>

      <aside class="customer-panel checkout-summary">
        <span class="eyebrow">STEP 3</span>
        <h2>Order review</h2>
        <div class="checkout-lines">
          <?php foreach ($cartData['items'] as $item): ?>
            <p><span><?= escapeHtml($item['title']) ?> × <?= (int) $item['quantity'] ?></span><strong><?= escapeHtml(formatGhs($item['line_total'])) ?></strong></p>
          <?php endforeach; ?>
        </div>
        <?php if ($quote !== null): ?>
          <p><span>Subtotal</span><strong><?= escapeHtml(formatGhs(CustomerCart::formatMoney($quote['subtotal_cents']))) ?></strong></p>
          <?php if ($quote['coupon_id'] !== null): ?><p><span>Discount</span><strong>−<?= escapeHtml(formatGhs(CustomerCart::formatMoney($quote['discount_cents']))) ?></strong></p><?php endif; ?>
          <p><span>Delivery</span><strong><?= escapeHtml(formatGhs(CustomerCart::formatMoney($quote['delivery_cents']))) ?></strong></p>
          <p><span>Tax</span><strong><?= escapeHtml(formatGhs(CustomerCart::formatMoney($quote['tax_cents']))) ?></strong></p>
          <p class="checkout-total"><span>Total due on delivery</span><strong><?= escapeHtml(formatGhs(CustomerCart::formatMoney($quote['total_cents']))) ?></strong></p>
          <button class="button button-dark" type="submit" name="action" value="place_order">Place Cash on Delivery order <span aria-hidden="true">→</span></button>
        <?php else: ?>
          <p class="cart-summary-note">Choose an address and update the order total before placing your order.</p>
          <button class="button button-dark" type="submit" name="action" value="review">Review order total <span aria-hidden="true">→</span></button>
        <?php endif; ?>
        <p class="cart-summary-note">Your order is unpaid until Cash on Delivery is collected. No payment is processed online.</p>
      </aside>
    </form>
  <?php endif; ?>
</main>
