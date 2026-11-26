<?php
declare(strict_types=1);

namespace Bookstore\Services;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

final class CustomerAccount
{
    public function __construct(private PDO $pdo)
    {
    }

    public function updateProfile(int $userId, string $firstName, string $lastName, string $email, ?string $phone): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 email_verified_at = CASE WHEN email <> :email_check THEN NULL ELSE email_verified_at END
             WHERE id = :user_id AND role = \'customer\''
        );
        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'email_check' => $email,
            'user_id' => $userId,
        ]);
        if ($statement->rowCount() === 0 && !$this->customerExists($userId)) {
            throw new RuntimeException('Customer account no longer exists.');
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): int
    {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare(
                'SELECT password_hash FROM users WHERE id = :user_id AND role = \'customer\' FOR UPDATE'
            );
            $query->execute(['user_id' => $userId]);
            $account = $query->fetch();
            if ($account === false) {
                throw new RuntimeException('Customer account no longer exists.');
            }
            if (!password_verify($currentPassword, $account['password_hash'])) {
                throw new DomainException('The current password is incorrect.');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('Password hashing failed.');
            }

            $update = $this->pdo->prepare(
                'UPDATE users
                 SET password_hash = :password_hash, session_version = session_version + 1
                 WHERE id = :user_id AND role = \'customer\''
            );
            $update->execute(['password_hash' => $passwordHash, 'user_id' => $userId]);

            $versionQuery = $this->pdo->prepare('SELECT session_version FROM users WHERE id = :user_id');
            $versionQuery->execute(['user_id' => $userId]);
            $sessionVersion = $versionQuery->fetchColumn();
            if ($sessionVersion === false) {
                throw new RuntimeException('Could not reload the updated account.');
            }

            $this->pdo->commit();

            return (int) $sessionVersion;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function dashboardSummary(int $userId): array
    {
        $orders = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
        $orders->execute(['user_id' => $userId]);
        $wishlist = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM wishlist_items wi
             INNER JOIN wishlists w ON w.id = wi.wishlist_id
             WHERE w.user_id = :user_id'
        );
        $wishlist->execute(['user_id' => $userId]);
        $addresses = $this->pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id');
        $addresses->execute(['user_id' => $userId]);

        return [
            'orders' => (int) $orders->fetchColumn(),
            'wishlist_items' => (int) $wishlist->fetchColumn(),
            'addresses' => (int) $addresses->fetchColumn(),
        ];
    }

    public function countOrders(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function orders(int $userId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.order_number, o.placed_at, o.order_status, o.payment_status,
                    o.total_amount, COUNT(oi.id) AS item_lines,
                    COALESCE(SUM(oi.quantity), 0) AS item_quantity
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = :user_id
             GROUP BY o.id
             ORDER BY o.placed_at DESC, o.id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function addresses(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, recipient_name, phone, region, city, address_line, landmark,
                    delivery_instructions, is_default, created_at
             FROM addresses
             WHERE user_id = :user_id
             ORDER BY is_default DESC, created_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function address(int $userId, int $addressId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, recipient_name, phone, region, city, address_line, landmark,
                    delivery_instructions, is_default
             FROM addresses
             WHERE id = :address_id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['address_id' => $addressId, 'user_id' => $userId]);
        $address = $statement->fetch();

        return $address === false ? null : $address;
    }

    public function saveAddress(int $userId, ?int $addressId, array $address, bool $makeDefault): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->lockCustomer($userId);
            $existing = $addressId === null ? null : $this->address($userId, $addressId);
            if ($addressId !== null && $existing === null) {
                throw new DomainException('That delivery address could not be found.');
            }

            $default = $makeDefault || ($addressId === null && $this->addressCount($userId) === 0);
            if ($default) {
                $clearDefault = $this->pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id');
                $clearDefault->execute(['user_id' => $userId]);
            }

            if ($addressId === null) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO addresses
                        (user_id, recipient_name, phone, region, city, address_line, landmark, delivery_instructions, is_default)
                     VALUES
                        (:user_id, :recipient_name, :phone, :region, :city, :address_line, :landmark, :delivery_instructions, :is_default)'
                );
                $statement->execute([
                    'user_id' => $userId,
                    'recipient_name' => $address['recipient_name'],
                    'phone' => $address['phone'],
                    'region' => $address['region'],
                    'city' => $address['city'],
                    'address_line' => $address['address_line'],
                    'landmark' => $address['landmark'],
                    'delivery_instructions' => $address['delivery_instructions'],
                    'is_default' => $default ? 1 : 0,
                ]);
            } else {
                $statement = $this->pdo->prepare(
                    'UPDATE addresses
                     SET recipient_name = :recipient_name,
                         phone = :phone,
                         region = :region,
                         city = :city,
                         address_line = :address_line,
                         landmark = :landmark,
                         delivery_instructions = :delivery_instructions,
                         is_default = :is_default
                     WHERE id = :address_id AND user_id = :user_id'
                );
                $statement->execute([
                    'recipient_name' => $address['recipient_name'],
                    'phone' => $address['phone'],
                    'region' => $address['region'],
                    'city' => $address['city'],
                    'address_line' => $address['address_line'],
                    'landmark' => $address['landmark'],
                    'delivery_instructions' => $address['delivery_instructions'],
                    'is_default' => $default ? 1 : 0,
                    'address_id' => $addressId,
                    'user_id' => $userId,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function setDefaultAddress(int $userId, int $addressId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->lockCustomer($userId);
            if ($this->address($userId, $addressId) === null) {
                throw new DomainException('That delivery address could not be found.');
            }

            $clearDefault = $this->pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id');
            $clearDefault->execute(['user_id' => $userId]);
            $setDefault = $this->pdo->prepare(
                'UPDATE addresses SET is_default = 1 WHERE id = :address_id AND user_id = :user_id'
            );
            $setDefault->execute(['address_id' => $addressId, 'user_id' => $userId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteAddress(int $userId, int $addressId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->lockCustomer($userId);
            $address = $this->address($userId, $addressId);
            if ($address === null) {
                throw new DomainException('That delivery address could not be found.');
            }

            $delete = $this->pdo->prepare('DELETE FROM addresses WHERE id = :address_id AND user_id = :user_id');
            $delete->execute(['address_id' => $addressId, 'user_id' => $userId]);
            if ((int) $address['is_default'] === 1) {
                $next = $this->pdo->prepare(
                    'SELECT id FROM addresses WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT 1'
                );
                $next->execute(['user_id' => $userId]);
                $nextId = $next->fetchColumn();
                if ($nextId !== false) {
                    $promote = $this->pdo->prepare(
                        'UPDATE addresses SET is_default = 1 WHERE id = :address_id AND user_id = :user_id'
                    );
                    $promote->execute(['address_id' => (int) $nextId, 'user_id' => $userId]);
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

    public function wishlistItems(int $userId): array
    {
        $statement = $this->pdo->prepare(
            '            SELECT b.id, b.slug, b.title, b.cover_image, b.price, b.discount_price,
                   b.stock_quantity, b.status,
                   GROUP_CONCAT(DISTINCT a.name ORDER BY ba.author_order SEPARATOR \', \') AS authors,
                    wi.added_at
             FROM wishlists w
             INNER JOIN wishlist_items wi ON wi.wishlist_id = w.id
             INNER JOIN books b ON b.id = wi.book_id
             LEFT JOIN book_authors ba ON ba.book_id = b.id
             LEFT JOIN authors a ON a.id = ba.author_id
             WHERE w.user_id = :user_id
             GROUP BY b.id, wi.added_at
             ORDER BY wi.added_at DESC, b.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function addWishlistItem(int $userId, int $bookId): void
    {
        $this->pdo->beginTransaction();
        try {
            $book = $this->pdo->prepare('SELECT id FROM books WHERE id = :book_id AND status = \'active\' FOR UPDATE');
            $book->execute(['book_id' => $bookId]);
            if ($book->fetchColumn() === false) {
                throw new DomainException('This book is not currently available.');
            }

            $wishlist = $this->pdo->prepare(
                'INSERT INTO wishlists (user_id, name)
                 VALUES (:user_id, \'My wishlist\')
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $wishlist->execute(['user_id' => $userId]);
            $wishlistId = (int) $this->pdo->lastInsertId();

            $item = $this->pdo->prepare(
                'INSERT INTO wishlist_items (wishlist_id, book_id)
                 VALUES (:wishlist_id, :book_id)
                 ON DUPLICATE KEY UPDATE added_at = added_at'
            );
            $item->execute(['wishlist_id' => $wishlistId, 'book_id' => $bookId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function removeWishlistItem(int $userId, int $bookId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE wi FROM wishlist_items wi
             INNER JOIN wishlists w ON w.id = wi.wishlist_id
             WHERE w.user_id = :user_id AND wi.book_id = :book_id'
        );
        $statement->execute(['user_id' => $userId, 'book_id' => $bookId]);
    }

    private function customerExists(int $userId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM users WHERE id = :user_id AND role = \'customer\'');
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    private function addressCount(int $userId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    private function lockCustomer(int $userId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE id = :user_id AND role = \'customer\' FOR UPDATE');
        $statement->execute(['user_id' => $userId]);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('Customer account no longer exists.');
        }
    }
}
