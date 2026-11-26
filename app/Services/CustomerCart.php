<?php
declare(strict_types=1);

namespace Bookstore\Services;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

final class CustomerCart
{
    public function __construct(private PDO $pdo)
    {
    }

    public function items(int $userId): array
    {
        $cartId = $this->findCartId($userId);
        return $cartId === null ? [] : $this->cartItems($cartId);
    }

    public function pageData(int $userId): array
    {
        $items = $this->items($userId);
        $subtotalCents = 0;
        $itemCount = 0;
        foreach ($items as $item) {
            $unitCents = self::decimalCents($item['discount_price'] ?? $item['price']);
            $lineCents = $unitCents * (int) $item['quantity'];
            $item['unit_price'] = self::money($unitCents);
            $item['line_total'] = self::money($lineCents);
            $subtotalCents += $lineCents;
            $itemCount += (int) $item['quantity'];
        }
        unset($item);

        return [
            'items' => $items,
            'subtotal' => self::money($subtotalCents),
            'item_count' => $itemCount,
        ];
    }

    public function itemCount(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(ci.quantity), 0)
             FROM carts c
             INNER JOIN cart_items ci ON ci.cart_id = c.id
             WHERE c.user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function addItem(int $userId, int $bookId, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99) {
            throw new DomainException('Choose a quantity between 1 and 99.');
        }

        $this->pdo->beginTransaction();
        try {
            $cartId = $this->getOrCreateCartId($userId);
            $this->lockCart($cartId);
            $book = $this->lockBook($bookId);
            if ($book === null) {
                throw new DomainException('This book is not currently available.');
            }

            $existing = $this->pdo->prepare(
                'SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND book_id = :book_id'
            );
            $existing->execute(['cart_id' => $cartId, 'book_id' => $bookId]);
            $newQuantity = (int) $existing->fetchColumn() + $quantity;
            if ($newQuantity > 99 || $newQuantity > (int) $book['stock_quantity']) {
                throw new DomainException('The requested quantity exceeds the available stock.');
            }

            $item = $this->pdo->prepare(
                'INSERT INTO cart_items (cart_id, book_id, quantity)
                 VALUES (:cart_id, :book_id, :quantity)
                 ON DUPLICATE KEY UPDATE quantity = :new_quantity'
            );
            $item->execute([
                'cart_id' => $cartId,
                'book_id' => $bookId,
                'quantity' => $quantity,
                'new_quantity' => $newQuantity,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function updateItem(int $userId, int $bookId, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99) {
            throw new DomainException('Choose a quantity between 1 and 99.');
        }

        $this->pdo->beginTransaction();
        try {
            $cartId = $this->findCartId($userId);
            if ($cartId === null) {
                throw new DomainException('Your cart is empty.');
            }
            $this->lockCart($cartId);
            $book = $this->lockBook($bookId);
            if ($book === null) {
                throw new DomainException('This book is no longer available.');
            }
            if ($quantity > (int) $book['stock_quantity']) {
                throw new DomainException('The requested quantity exceeds the available stock.');
            }

            $update = $this->pdo->prepare(
                'UPDATE cart_items ci
                 INNER JOIN carts c ON c.id = ci.cart_id
                 SET ci.quantity = :quantity
                 WHERE c.user_id = :user_id AND ci.book_id = :book_id AND c.id = :cart_id'
            );
            $update->execute([
                'quantity' => $quantity,
                'user_id' => $userId,
                'book_id' => $bookId,
                'cart_id' => $cartId,
            ]);
            if ($update->rowCount() === 0) {
                $exists = $this->pdo->prepare(
                    'SELECT 1 FROM cart_items WHERE cart_id = :cart_id AND book_id = :book_id'
                );
                $exists->execute(['cart_id' => $cartId, 'book_id' => $bookId]);
                if ($exists->fetchColumn() === false) {
                    throw new DomainException('That book is not in your cart.');
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function removeItem(int $userId, int $bookId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE ci FROM cart_items ci
             INNER JOIN carts c ON c.id = ci.cart_id
             WHERE c.user_id = :user_id AND ci.book_id = :book_id'
        );
        $statement->execute(['user_id' => $userId, 'book_id' => $bookId]);
    }

    public function quote(int $userId, int $addressId, string $couponCode): array
    {
        $cartId = $this->findCartId($userId);
        if ($cartId === null) {
            throw new DomainException('Your cart is empty.');
        }

        return $this->buildQuote($userId, $cartId, $addressId, $couponCode, false);
    }

    public function placeOrder(int $userId, int $addressId, string $couponCode, array $expectedQuote): string
    {
        $this->pdo->beginTransaction();
        try {
            $cartId = $this->getOrCreateCartId($userId);
            $this->lockCart($cartId);
            $quote = $this->buildQuote($userId, $cartId, $addressId, $couponCode, true);
            foreach (['subtotal_cents', 'discount_cents', 'delivery_cents', 'tax_cents', 'total_cents', 'coupon_id', 'currency', 'items_signature'] as $key) {
                if (!array_key_exists($key, $expectedQuote) || $expectedQuote[$key] !== $quote[$key]) {
                    throw new DomainException('Your order total changed. Review the updated total before placing the order.');
                }
            }
            $items = $this->cartItems($cartId, true);
            if ($items === []) {
                throw new DomainException('Your cart is empty.');
            }

            $customerQuery = $this->pdo->prepare(
                'SELECT first_name, last_name, email FROM users WHERE id = :user_id AND role = \'customer\' AND status = \'active\''
            );
            $customerQuery->execute(['user_id' => $userId]);
            $customer = $customerQuery->fetch();
            if ($customer === false) {
                throw new DomainException('Your account is not available to place an order.');
            }

            $addressQuery = $this->pdo->prepare(
                'SELECT recipient_name, phone, region, city, address_line, landmark, delivery_instructions
                 FROM addresses WHERE id = :address_id AND user_id = :user_id FOR UPDATE'
            );
            $addressQuery->execute(['address_id' => $addressId, 'user_id' => $userId]);
            $address = $addressQuery->fetch();
            if ($address === false) {
                throw new DomainException('Choose one of your saved delivery addresses.');
            }

            $orderNumber = 'RR-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
            $insertOrder = $this->pdo->prepare(
                'INSERT INTO orders
                    (order_number, user_id, coupon_id, currency, subtotal, discount_amount, delivery_fee,
                     tax_amount, total_amount, payment_method, payment_status, order_status,
                     recipient_name, recipient_email, recipient_phone, delivery_region, delivery_city,
                     delivery_address, delivery_landmark, delivery_instructions)
                 VALUES
                    (:order_number, :user_id, :coupon_id, :currency, :subtotal, :discount_amount, :delivery_fee,
                     :tax_amount, :total_amount, \'cash_on_delivery\', \'unpaid\', \'pending\',
                     :recipient_name, :recipient_email, :recipient_phone, :delivery_region, :delivery_city,
                     :delivery_address, :delivery_landmark, :delivery_instructions)'
            );
            $insertOrder->execute([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'coupon_id' => $quote['coupon_id'],
                'currency' => $quote['currency'],
                'subtotal' => self::money($quote['subtotal_cents']),
                'discount_amount' => self::money($quote['discount_cents']),
                'delivery_fee' => self::money($quote['delivery_cents']),
                'tax_amount' => self::money($quote['tax_cents']),
                'total_amount' => self::money($quote['total_cents']),
                'recipient_name' => $address['recipient_name'],
                'recipient_email' => $customer['email'],
                'recipient_phone' => $address['phone'],
                'delivery_region' => $address['region'],
                'delivery_city' => $address['city'],
                'delivery_address' => $address['address_line'],
                'delivery_landmark' => $address['landmark'],
                'delivery_instructions' => $address['delivery_instructions'],
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $insertItem = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, book_id, book_title, isbn, quantity, unit_price, line_total)
                 VALUES (:order_id, :book_id, :book_title, :isbn, :quantity, :unit_price, :line_total)'
            );
            $deductStock = $this->pdo->prepare(
                'UPDATE books SET stock_quantity = stock_quantity - :quantity
                 WHERE id = :book_id AND status = \'active\' AND stock_quantity >= :minimum_stock'
            );
            $inventory = $this->pdo->prepare(
                'INSERT INTO inventory_logs
                    (book_id, administrator_id, movement_type, quantity, previous_stock, new_stock, reason)
                 VALUES (:book_id, NULL, \'sale\', :quantity, :previous_stock, :new_stock, :reason)'
            );

            foreach ($items as &$item) {
                $quantity = (int) $item['quantity'];
                $stock = (int) $item['stock_quantity'];
                if ($item['status'] !== 'active' || $quantity > $stock) {
                    throw new DomainException('A cart item is no longer available in the requested quantity.');
                }
                $unitCents = self::decimalCents($item['discount_price'] ?? $item['price']);
                $lineCents = $unitCents * $quantity;
                $deductStock->execute([
                    'quantity' => $quantity,
                    'book_id' => (int) $item['book_id'],
                    'minimum_stock' => $quantity,
                ]);
                if ($deductStock->rowCount() !== 1) {
                    throw new DomainException('A cart item ran out of stock. Review your cart and try again.');
                }
                $insertItem->execute([
                    'order_id' => $orderId,
                    'book_id' => (int) $item['book_id'],
                    'book_title' => $item['title'],
                    'isbn' => $item['isbn'],
                    'quantity' => $quantity,
                    'unit_price' => self::money($unitCents),
                    'line_total' => self::money($lineCents),
                ]);
                $inventory->execute([
                    'book_id' => (int) $item['book_id'],
                    'quantity' => $quantity,
                    'previous_stock' => $stock,
                    'new_stock' => $stock - $quantity,
                    'reason' => 'Customer order ' . $orderNumber,
                ]);
            }

            $payment = $this->pdo->prepare(
                'INSERT INTO payments (order_id, provider, amount, currency, status)
                 VALUES (:order_id, \'cash_on_delivery\', :amount, :currency, \'pending\')'
            );
            $payment->execute([
                'order_id' => $orderId,
                'amount' => self::money($quote['total_cents']),
                'currency' => $quote['currency'],
            ]);
            $shipment = $this->pdo->prepare('INSERT INTO shipments (order_id, status) VALUES (:order_id, \'pending\')');
            $shipment->execute(['order_id' => $orderId]);

            if ($quote['coupon_id'] !== null) {
                $usage = $this->pdo->prepare(
                    'INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount)
                     VALUES (:coupon_id, :user_id, :order_id, :discount_amount)'
                );
                $usage->execute([
                    'coupon_id' => $quote['coupon_id'],
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'discount_amount' => self::money($quote['discount_cents']),
                ]);
            }

            $clearCart = $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $clearCart->execute(['cart_id' => $cartId]);
            $this->pdo->commit();

            return $orderNumber;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function orderForCustomer(int $userId, string $orderNumber): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT order_number, currency, subtotal, discount_amount, delivery_fee, tax_amount, total_amount,
                    payment_method, payment_status, order_status, placed_at
             FROM orders WHERE user_id = :user_id AND order_number = :order_number LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'order_number' => $orderNumber]);
        $order = $statement->fetch();

        return $order === false ? null : $order;
    }

    public function orderItemsForCustomer(int $userId, string $orderNumber): array
    {
        $statement = $this->pdo->prepare(
            'SELECT oi.book_title, oi.isbn, oi.quantity, oi.unit_price, oi.line_total
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = :user_id AND o.order_number = :order_number
             ORDER BY oi.id'
        );
        $statement->execute(['user_id' => $userId, 'order_number' => $orderNumber]);

        return $statement->fetchAll();
    }

    public static function formatMoney(int $cents): string
    {
        return self::money($cents);
    }

    private function buildQuote(int $userId, int $cartId, int $addressId, string $couponCode, bool $lockCoupon): array
    {
        $addressQuery = $this->pdo->prepare(
            'SELECT region, city FROM addresses WHERE id = :address_id AND user_id = :user_id' . ($lockCoupon ? ' FOR UPDATE' : '')
        );
        $addressQuery->execute(['address_id' => $addressId, 'user_id' => $userId]);
        $address = $addressQuery->fetch();
        if ($address === false) {
            throw new DomainException('Choose one of your saved delivery addresses.');
        }

        $items = $this->cartItems($cartId, $lockCoupon);
        if ($items === []) {
            throw new DomainException('Your cart is empty.');
        }
        $subtotalCents = 0;
        $cartLines = [];
        foreach ($items as $item) {
            if ($item['status'] !== 'active') {
                throw new DomainException('A cart item is no longer available.');
            }
            $quantity = (int) $item['quantity'];
            if ($quantity > (int) $item['stock_quantity']) {
                throw new DomainException('A cart item exceeds the available stock.');
            }
            $unitCents = self::decimalCents($item['discount_price'] ?? $item['price']);
            $lineCents = $unitCents * $quantity;
            $subtotalCents += $lineCents;
            $cartLines[] = [(int) $item['book_id'], $quantity, $unitCents, $item['title'], $item['isbn']];
        }

        $settings = $this->settings();
        $deliveryCents = $this->deliveryFee($address['region'], $address['city'], $settings);
        $coupon = $this->coupon($userId, strtoupper(trim($couponCode)), $subtotalCents, $lockCoupon);
        $discountCents = $coupon['discount_cents'];
        $taxableCents = max(0, $subtotalCents - $discountCents);
        $taxBasisPoints = self::decimalCents($settings['tax_rate_percent'] ?? '0');
        if ($taxBasisPoints > 10000) {
            throw new RuntimeException('The configured tax rate is invalid.');
        }
        $taxCents = (int) intdiv(($taxableCents * $taxBasisPoints) + 5000, 10000);
        $currency = $settings['currency'] ?? 'GHS';
        if (!is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new RuntimeException('The configured currency is invalid.');
        }

        return [
            'items' => $items,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'delivery_cents' => $deliveryCents,
            'tax_cents' => $taxCents,
            'total_cents' => max(0, $subtotalCents - $discountCents + $deliveryCents + $taxCents),
            'coupon_id' => $coupon['coupon_id'],
            'currency' => $currency,
            'items_signature' => hash('sha256', serialize($cartLines)),
        ];
    }

    private function coupon(int $userId, string $code, int $subtotalCents, bool $lock): array
    {
        if ($code === '') {
            return ['coupon_id' => null, 'discount_cents' => 0];
        }

        $statement = $this->pdo->prepare(
            'SELECT id, discount_type, discount_value, minimum_subtotal, maximum_discount,
                    usage_limit, per_user_limit
             FROM coupons
             WHERE code = :code AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
               AND (expires_at IS NULL OR expires_at >= CURRENT_TIMESTAMP)
             LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['code' => $code]);
        $coupon = $statement->fetch();
        if ($coupon === false) {
            throw new DomainException('That coupon is invalid or has expired.');
        }
        if ($subtotalCents < self::decimalCents($coupon['minimum_subtotal'])) {
            throw new DomainException('Your cart does not meet the minimum subtotal for that coupon.');
        }

        $usage = $this->pdo->prepare(
            'SELECT COUNT(*) AS all_uses,
                    SUM(user_id = :user_id) AS customer_uses
             FROM coupon_usages WHERE coupon_id = :coupon_id'
        );
        $usage->execute(['user_id' => $userId, 'coupon_id' => (int) $coupon['id']]);
        $counts = $usage->fetch();
        if (
            ($coupon['usage_limit'] !== null && (int) $counts['all_uses'] >= (int) $coupon['usage_limit'])
            || ($coupon['per_user_limit'] !== null && (int) $counts['customer_uses'] >= (int) $coupon['per_user_limit'])
        ) {
            throw new DomainException('That coupon has reached its usage limit.');
        }

        $value = self::decimalCents($coupon['discount_value']);
        $discount = $coupon['discount_type'] === 'percentage'
            ? (int) intdiv(($subtotalCents * $value) + 5000, 10000)
            : $value;
        if ($coupon['maximum_discount'] !== null) {
            $discount = min($discount, self::decimalCents($coupon['maximum_discount']));
        }

        return [
            'coupon_id' => (int) $coupon['id'],
            'discount_cents' => min($subtotalCents, $discount),
        ];
    }

    private function deliveryFee(string $region, string $city, array $settings): int
    {
        $statement = $this->pdo->prepare(
            'SELECT fee FROM delivery_zones
             WHERE is_active = 1 AND region = :region
               AND (city = :city_exact OR city = :city_wildcard)
             ORDER BY CASE WHEN city = :city_order THEN 0 ELSE 1 END
             LIMIT 1'
        );
        $statement->execute([
            'region' => $region,
            'city_exact' => $city,
            'city_wildcard' => '*',
            'city_order' => $city,
        ]);
        $fee = $statement->fetchColumn();

        return $fee === false
            ? self::decimalCents($settings['default_delivery_fee'] ?? '0')
            : self::decimalCents($fee);
    }

    private function settings(): array
    {
        $statement = $this->pdo->query(
            'SELECT setting_key, setting_value FROM site_settings
             WHERE setting_key IN (\'currency\', \'tax_rate_percent\', \'default_delivery_fee\')'
        );
        $settings = [];
        foreach ($statement->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    private function cartItems(int $cartId, bool $lock = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ci.book_id, ci.quantity, b.title, b.isbn, b.slug, b.cover_image, b.price,
                    b.discount_price, b.stock_quantity, b.status
             FROM cart_items ci
             INNER JOIN books b ON b.id = ci.book_id
             WHERE ci.cart_id = :cart_id
             ORDER BY b.id' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['cart_id' => $cartId]);

        return $statement->fetchAll();
    }

    private function findCartId(int $userId): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $cartId = $statement->fetchColumn();

        return $cartId === false ? null : (int) $cartId;
    }

    private function getOrCreateCartId(int $userId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO carts (user_id) VALUES (:user_id)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $statement->execute(['user_id' => $userId]);
        $cartId = (int) $this->pdo->lastInsertId();
        if ($cartId < 1) {
            throw new RuntimeException('Could not open the customer cart.');
        }

        return $cartId;
    }

    private function lockCart(int $cartId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM carts WHERE id = :cart_id FOR UPDATE');
        $statement->execute(['cart_id' => $cartId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('Customer cart no longer exists.');
        }
    }

    private function lockBook(int $bookId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, stock_quantity, status FROM books WHERE id = :book_id AND status = \'active\' FOR UPDATE'
        );
        $statement->execute(['book_id' => $bookId]);
        $book = $statement->fetch();

        return $book === false ? null : $book;
    }

    private static function decimalCents(string|int|float $value): int
    {
        $value = (string) $value;
        if (preg_match('/\A([0-9]+)(?:\.([0-9]{1,2}))?\z/D', $value, $matches) !== 1) {
            throw new RuntimeException('A stored monetary value is invalid.');
        }
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ((int) $matches[1] * 100) + (int) $fraction;
    }

    private static function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
