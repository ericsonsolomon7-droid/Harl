<?php
declare(strict_types=1);

namespace Bookstore\Services;

use DomainException;
use PDO;
use RuntimeException;
use Throwable;

final class Administration
{
    public function __construct(private PDO $pdo)
    {
    }

    public function dashboard(): array
    {
        $summary = [
            'revenue' => $this->pdo->query(
                'SELECT COALESCE(SUM(total_amount), 0) FROM orders
                 WHERE payment_status = \'paid\' AND order_status <> \'cancelled\''
            )->fetchColumn(),
            'orders' => $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'customers' => $this->pdo->query('SELECT COUNT(*) FROM users WHERE role = \'customer\'')->fetchColumn(),
            'books' => $this->pdo->query('SELECT COUNT(*) FROM books')->fetchColumn(),
            'pending_orders' => $this->pdo->query(
                'SELECT COUNT(*) FROM orders WHERE order_status IN (\'pending\', \'confirmed\', \'processing\')'
            )->fetchColumn(),
            'completed_orders' => $this->pdo->query(
                'SELECT COUNT(*) FROM orders WHERE order_status = \'delivered\''
            )->fetchColumn(),
            'low_stock' => $this->pdo->query(
                'SELECT COUNT(*) FROM books WHERE status = \'active\' AND stock_quantity <= low_stock_threshold'
            )->fetchColumn(),
        ];

        $recent = $this->pdo->query(
            'SELECT order_number, recipient_name, total_amount, payment_status, order_status, placed_at
             FROM orders ORDER BY placed_at DESC, id DESC LIMIT 8'
        )->fetchAll();
        $lowStockBooks = $this->pdo->query(
            'SELECT title, isbn, stock_quantity, low_stock_threshold
             FROM books
             WHERE status = \'active\' AND stock_quantity <= low_stock_threshold
             ORDER BY stock_quantity ASC, title ASC LIMIT 8'
        )->fetchAll();

        return [
            'summary' => array_map('intval', array_diff_key($summary, ['revenue' => true])),
            'revenue' => (string) $summary['revenue'],
            'recent_orders' => $recent,
            'low_stock_books' => $lowStockBooks,
        ];
    }

    public function countOrders(?string $status, string $search): int
    {
        [$where, $parameters] = $this->orderFilter($status, $search);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM orders o ' . $where);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function orders(?string $status, string $search, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->orderFilter($status, $search);
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.order_number, o.recipient_name, o.recipient_email, o.recipient_phone,
                    o.delivery_region, o.delivery_city, o.delivery_address, o.total_amount,
                    o.payment_method, o.payment_status, o.order_status, o.placed_at
             FROM orders o ' . $where . '
             ORDER BY o.placed_at DESC, o.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function updateOrderStatus(int $administratorId, int $orderId, string $newStatus): void
    {
        $allowedStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new DomainException('Choose a valid order status.');
        }

        $this->pdo->beginTransaction();
        try {
            $order = $this->lockOrder($orderId);
            $currentStatus = $order['order_status'];
            $transitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['processing', 'cancelled'],
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered'],
                'delivered' => [],
                'cancelled' => [],
            ];
            if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)) {
                throw new DomainException('That order status transition is not allowed.');
            }

            if ($newStatus === 'cancelled') {
                if ($order['payment_status'] !== 'unpaid') {
                    throw new DomainException('Paid orders require payment reconciliation before cancellation.');
                }
                $this->restoreOrderStock($administratorId, $orderId, (string) $order['order_number']);
                $shipment = $this->pdo->prepare(
                    'UPDATE shipments SET status = \'failed\', notes = \'Cancelled before dispatch\'
                     WHERE order_id = :order_id AND status = \'pending\''
                );
                $shipment->execute(['order_id' => $orderId]);
            } elseif ($newStatus === 'shipped') {
                $shipment = $this->pdo->prepare(
                    'UPDATE shipments SET status = \'shipped\', shipped_at = CURRENT_TIMESTAMP
                     WHERE order_id = :order_id AND status = \'pending\''
                );
                $shipment->execute(['order_id' => $orderId]);
                if ($shipment->rowCount() !== 1) {
                    throw new DomainException('The shipment is not ready to be marked shipped.');
                }
            } elseif ($newStatus === 'delivered') {
                $shipment = $this->pdo->prepare(
                    'UPDATE shipments SET status = \'delivered\', delivered_at = CURRENT_TIMESTAMP
                     WHERE order_id = :order_id AND status IN (\'shipped\', \'in_transit\')'
                );
                $shipment->execute(['order_id' => $orderId]);
                if ($shipment->rowCount() !== 1) {
                    throw new DomainException('The shipment must be marked shipped before delivery.');
                }
            }

            $update = $this->pdo->prepare('UPDATE orders SET order_status = :status WHERE id = :order_id');
            $update->execute(['status' => $newStatus, 'order_id' => $orderId]);
            $this->logActivity($administratorId, 'order.status_updated', $orderId, [
                'from' => $currentStatus,
                'to' => $newStatus,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function recordCodPayment(int $administratorId, int $orderId): void
    {
        $this->pdo->beginTransaction();
        try {
            $order = $this->lockOrder($orderId);
            if ($order['payment_method'] !== 'cash_on_delivery') {
                throw new DomainException('Only Cash on Delivery collections can be recorded here.');
            }
            if ($order['order_status'] === 'cancelled' || $order['payment_status'] !== 'unpaid') {
                throw new DomainException('This order is not awaiting Cash on Delivery collection.');
            }

            $payment = $this->pdo->prepare(
                'UPDATE payments SET status = \'paid\', paid_at = CURRENT_TIMESTAMP
                 WHERE order_id = :order_id AND provider = \'cash_on_delivery\' AND status = \'pending\''
            );
            $payment->execute(['order_id' => $orderId]);
            if ($payment->rowCount() !== 1) {
                throw new DomainException('The pending Cash on Delivery payment record could not be found.');
            }

            $update = $this->pdo->prepare('UPDATE orders SET payment_status = \'paid\' WHERE id = :order_id');
            $update->execute(['order_id' => $orderId]);
            $this->logActivity($administratorId, 'order.cod_collected', $orderId, [
                'order_number' => (string) $order['order_number'],
                'amount' => (string) $order['total_amount'],
                'currency' => (string) $order['currency'],
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function sellerApplications(?string $status, string $search, int $limit, int $offset): array
    {
        $where = 'WHERE 1 = 1';
        $parameters = [];
        if ($status !== null) {
            $where .= ' AND s.verification_status = :seller_status';
            $parameters['seller_status'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (s.store_name LIKE :store_search OR u.email LIKE :email_search OR CONCAT(u.first_name, \' \', u.last_name) LIKE :name_search)';
            $like = '%' . $search . '%';
            $parameters['store_search'] = $like;
            $parameters['email_search'] = $like;
            $parameters['name_search'] = $like;
        }
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.store_name, s.seller_type, s.region, s.city, s.verification_status,
                    s.created_at, u.first_name, u.last_name, u.email, u.phone,
                    sa.id AS application_id, sa.status AS application_status, sa.admin_notes,
                    sa.rejection_reason, sa.submitted_at, sa.reviewed_at
             FROM sellers s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN seller_applications sa ON sa.id = (
                 SELECT MAX(current_application.id) FROM seller_applications current_application
                 WHERE current_application.seller_id = s.id
             ) ' . $where . '
             ORDER BY sa.submitted_at DESC, s.id DESC
             LIMIT :result_limit OFFSET :result_offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countSellerApplications(?string $status, string $search): int
    {
        $where = 'WHERE 1 = 1';
        $parameters = [];
        if ($status !== null) {
            $where .= ' AND s.verification_status = :seller_status';
            $parameters['seller_status'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (s.store_name LIKE :store_search OR u.email LIKE :email_search OR CONCAT(u.first_name, \' \', u.last_name) LIKE :name_search)';
            $like = '%' . $search . '%';
            $parameters['store_search'] = $like;
            $parameters['email_search'] = $like;
            $parameters['name_search'] = $like;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sellers s INNER JOIN users u ON u.id = s.user_id ' . $where
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function reviewSeller(int $administratorId, int $sellerId, string $action, string $reason, string $notes): void
    {
        $statusByAction = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'review' => 'under_review'];
        if (!isset($statusByAction[$action])) {
            throw new DomainException('Choose a valid seller action.');
        }
        if (in_array($action, ['reject', 'suspend'], true) && trim($reason) === '') {
            throw new DomainException('A reason is required for this seller action.');
        }

        $this->pdo->beginTransaction();
        try {
            $sellerQuery = $this->pdo->prepare(
                'SELECT id, user_id, verification_status, store_name FROM sellers WHERE id = :seller_id FOR UPDATE'
            );
            $sellerQuery->execute(['seller_id' => $sellerId]);
            $seller = $sellerQuery->fetch();
            if ($seller === false) {
                throw new DomainException('That seller application could not be found.');
            }
            $newStatus = $statusByAction[$action];
            $updateSeller = $this->pdo->prepare(
                'UPDATE sellers SET verification_status = :status,
                    verified_at = CASE WHEN :approved_timestamp = 1 THEN CURRENT_TIMESTAMP ELSE verified_at END,
                    verified_by = CASE WHEN :approved_reviewer = 1 THEN :administrator_id ELSE verified_by END
                 WHERE id = :seller_id'
            );
            $updateSeller->execute([
                'status' => $newStatus,
                'approved_timestamp' => $action === 'approve' ? 1 : 0,
                'approved_reviewer' => $action === 'approve' ? 1 : 0,
                'administrator_id' => $administratorId,
                'seller_id' => $sellerId,
            ]);
            $applicationIdQuery = $this->pdo->prepare(
                'SELECT id FROM seller_applications WHERE seller_id = :seller_id ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $applicationIdQuery->execute(['seller_id' => $sellerId]);
            $applicationId = $applicationIdQuery->fetchColumn();
            if ($applicationId === false) {
                throw new DomainException('The seller application record could not be found.');
            }
            $application = $this->pdo->prepare(
                'UPDATE seller_applications
                 SET status = :status, admin_notes = :admin_notes, rejection_reason = :rejection_reason,
                     reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
                 WHERE id = :application_id'
            );
            $application->execute([
                'status' => $newStatus,
                'admin_notes' => $notes !== '' ? $notes : null,
                'rejection_reason' => in_array($action, ['reject', 'suspend'], true) ? $reason : null,
                'reviewed_by' => $administratorId,
                'application_id' => (int) $applicationId,
            ]);
            $message = match ($action) {
                'approve' => 'Your seller application has been approved. You may now submit books for review.',
                'reject' => 'Your seller application was rejected. Administrator reason: ' . $reason,
                'suspend' => 'Your seller account has been suspended. Administrator reason: ' . $reason,
                default => 'Your seller application is now under administrator review.',
            };
            $this->notifySeller($sellerId, 'Seller application update', $message, 'application_' . $newStatus);
            $this->logMarketplaceActivity($administratorId, 'seller.' . $action, 'seller', $sellerId, [
                'from' => $seller['verification_status'], 'to' => $newStatus, 'reason' => $reason,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reviewBook(int $administratorId, int $bookId, string $action, string $reason): void
    {
        $statusByAction = ['approve' => 'approved', 'reject' => 'rejected', 'revision' => 'revision_requested', 'suspend' => 'suspended'];
        if (!isset($statusByAction[$action])) {
            throw new DomainException('Choose a valid book action.');
        }
        if (in_array($action, ['reject', 'revision', 'suspend'], true) && trim($reason) === '') {
            throw new DomainException('A reason is required for this book action.');
        }

        $this->pdo->beginTransaction();
        try {
            $bookQuery = $this->pdo->prepare(
                'SELECT id, seller_id, title, status FROM books WHERE id = :book_id FOR UPDATE'
            );
            $bookQuery->execute(['book_id' => $bookId]);
            $book = $bookQuery->fetch();
            if ($book === false) {
                throw new DomainException('That book submission could not be found.');
            }
            $newStatus = $statusByAction[$action];
            $update = $this->pdo->prepare(
                'UPDATE books SET status = :status, reviewed_by = :reviewed_by,
                    reviewed_at = CURRENT_TIMESTAMP, review_reason = :review_reason
                 WHERE id = :book_id'
            );
            $update->execute([
                'status' => $newStatus,
                'reviewed_by' => $administratorId,
                'review_reason' => $reason !== '' ? $reason : null,
                'book_id' => $bookId,
            ]);
            $history = $this->pdo->prepare(
                'INSERT INTO book_approval_history
                    (book_id, seller_id, administrator_id, previous_status, new_status, action, reason)
                 VALUES (:book_id, :seller_id, :administrator_id, :previous_status, :new_status, :action, :reason)'
            );
            $history->execute([
                'book_id' => $bookId,
                'seller_id' => $book['seller_id'],
                'administrator_id' => $administratorId,
                'previous_status' => $book['status'],
                'new_status' => $newStatus,
                'action' => $action,
                'reason' => $reason !== '' ? $reason : null,
            ]);
            if ($book['seller_id'] !== null) {
                $message = match ($action) {
                    'approve' => 'Your book "' . $book['title'] . '" has been approved.',
                    'reject' => 'Your book "' . $book['title'] . '" was rejected: ' . $reason,
                    'revision' => 'Corrections are required for "' . $book['title'] . '": ' . $reason,
                    default => 'Your book "' . $book['title'] . '" has been suspended: ' . $reason,
                };
                $this->notifySeller((int) $book['seller_id'], 'Book review update', $message, 'book_' . $newStatus);
            }
            $this->logMarketplaceActivity($administratorId, 'book.' . $action, 'book', $bookId, [
                'from' => $book['status'], 'to' => $newStatus, 'reason' => $reason,
            ]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function bookSubmissions(?string $status, string $search, int $limit, int $offset): array
    {
        $where = 'WHERE b.seller_id IS NOT NULL';
        $parameters = [];
        if ($status !== null) {
            $where .= ' AND b.status = :book_status';
            $parameters['book_status'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (b.title LIKE :book_search OR s.store_name LIKE :seller_search OR b.isbn LIKE :isbn_search)';
            $like = '%' . $search . '%';
            $parameters['book_search'] = $like;
            $parameters['seller_search'] = $like;
            $parameters['isbn_search'] = $like;
        }
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.title, b.isbn, b.price, b.discount_price, b.stock_quantity,
                    b.cover_image, b.status, b.created_at, b.submitted_at, b.review_reason,
                    s.id AS seller_id, s.store_name, s.verification_status,
                    c.name AS category_name
             FROM books b
             INNER JOIN sellers s ON s.id = b.seller_id
             LEFT JOIN categories c ON c.id = b.category_id ' . $where . '
             ORDER BY COALESCE(b.submitted_at, b.created_at) DESC, b.id DESC
             LIMIT :result_limit OFFSET :result_offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countBookSubmissions(?string $status, string $search): int
    {
        $where = 'WHERE b.seller_id IS NOT NULL';
        $parameters = [];
        if ($status !== null) {
            $where .= ' AND b.status = :book_status';
            $parameters['book_status'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (b.title LIKE :book_search OR s.store_name LIKE :seller_search OR b.isbn LIKE :isbn_search)';
            $like = '%' . $search . '%';
            $parameters['book_search'] = $like;
            $parameters['seller_search'] = $like;
            $parameters['isbn_search'] = $like;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM books b INNER JOIN sellers s ON s.id = b.seller_id ' . $where
        );
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    private function notifySeller(int $sellerId, string $title, string $message, string $type): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO seller_notifications (seller_id, title, message, notification_type)
             VALUES (:seller_id, :title, :message, :notification_type)'
        );
        $statement->execute([
            'seller_id' => $sellerId, 'title' => $title, 'message' => $message, 'notification_type' => $type,
        ]);
    }

    private function logMarketplaceActivity(int $administratorId, string $action, string $entityType, int $entityId, array $details): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, details)
             VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :details)'
        );
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipAddress = is_string($remoteAddress) ? inet_pton($remoteAddress) : false;
        $statement->execute([
            'user_id' => $administratorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $ipAddress === false ? null : $ipAddress,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ]);
    }

    private function lockOrder(int $orderId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, order_number, order_status, payment_method, payment_status, total_amount, currency
             FROM orders WHERE id = :order_id FOR UPDATE'
        );
        $statement->execute(['order_id' => $orderId]);
        $order = $statement->fetch();
        if ($order === false) {
            throw new DomainException('That order could not be found.');
        }

        return $order;
    }

    private function restoreOrderStock(int $administratorId, int $orderId, string $orderNumber): void
    {
        $items = $this->pdo->prepare(
            'SELECT book_id, quantity FROM order_items WHERE order_id = :order_id ORDER BY book_id FOR UPDATE'
        );
        $items->execute(['order_id' => $orderId]);
        $restore = $this->pdo->prepare('UPDATE books SET stock_quantity = :new_stock WHERE id = :book_id');
        $movement = $this->pdo->prepare(
            'INSERT INTO inventory_logs
                (book_id, administrator_id, movement_type, quantity, previous_stock, new_stock, reason)
             VALUES (:book_id, :administrator_id, \'cancellation\', :quantity, :previous_stock, :new_stock, :reason)'
        );

        foreach ($items->fetchAll() as $item) {
            if ($item['book_id'] === null) {
                continue;
            }
            $book = $this->pdo->prepare('SELECT stock_quantity FROM books WHERE id = :book_id FOR UPDATE');
            $book->execute(['book_id' => (int) $item['book_id']]);
            $previousStock = $book->fetchColumn();
            if ($previousStock === false) {
                continue;
            }

            $quantity = (int) $item['quantity'];
            $newStock = (int) $previousStock + $quantity;
            if ($newStock > 4294967295) {
                throw new RuntimeException('Restoring this order would exceed the supported inventory range.');
            }
            $restore->execute(['new_stock' => $newStock, 'book_id' => (int) $item['book_id']]);
            $movement->execute([
                'book_id' => (int) $item['book_id'],
                'administrator_id' => $administratorId,
                'quantity' => $quantity,
                'previous_stock' => (int) $previousStock,
                'new_stock' => $newStock,
                'reason' => 'Cancelled order ' . $orderNumber,
            ]);
        }
    }

    private function logActivity(int $administratorId, string $action, int $orderId, array $details): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, details)
             VALUES (:user_id, :action, \'order\', :entity_id, :ip_address, :details)'
        );
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipAddress = is_string($remoteAddress) ? inet_pton($remoteAddress) : false;
        $json = json_encode($details, JSON_THROW_ON_ERROR);
        $statement->bindValue(':user_id', $administratorId, PDO::PARAM_INT);
        $statement->bindValue(':action', $action, PDO::PARAM_STR);
        $statement->bindValue(':entity_id', $orderId, PDO::PARAM_INT);
        $statement->bindValue(':ip_address', $ipAddress === false ? null : $ipAddress, $ipAddress === false ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':details', $json, PDO::PARAM_STR);
        $statement->execute();
    }

    private function orderFilter(?string $status, string $search): array
    {
        $where = 'WHERE 1 = 1';
        $parameters = [];
        if ($status !== null) {
            $where .= ' AND o.order_status = :status';
            $parameters[':status'] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (o.order_number LIKE :search_number OR o.recipient_email LIKE :search_email OR o.recipient_name LIKE :search_name)';
            $like = '%' . $search . '%';
            $parameters[':search_number'] = $like;
            $parameters[':search_email'] = $like;
            $parameters[':search_name'] = $like;
        }

        return [$where, $parameters];
    }
}
