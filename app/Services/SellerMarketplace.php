<?php
declare(strict_types=1);

namespace Bookstore\Services;

use PDO;

final class SellerMarketplace
{
    public function __construct(private PDO $pdo)
    {
    }

    public function sellerForUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.user_id, s.store_name, s.seller_type, s.description, s.business_logo,
                    s.phone, s.region, s.city, s.business_address, s.verification_status,
                    s.verified_at, s.created_at, sa.status AS application_status,
                    sa.admin_notes, sa.rejection_reason, sa.submitted_at, sa.reviewed_at
             FROM sellers s
             LEFT JOIN seller_applications sa ON sa.id = (
                 SELECT MAX(current_application.id) FROM seller_applications current_application
                 WHERE current_application.seller_id = s.id
             )
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $seller = $statement->fetch();

        return $seller === false ? null : $seller;
    }

    public function dashboard(int $sellerId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM books WHERE seller_id = :seller_id_1) AS submitted_books,
                (SELECT COUNT(*) FROM books WHERE seller_id = :seller_id_2 AND status = \'approved\') AS approved_books,
                (SELECT COUNT(*) FROM books WHERE seller_id = :seller_id_3 AND status IN (\'pending_review\', \'under_review\')) AS pending_books,
                (SELECT COUNT(*) FROM books WHERE seller_id = :seller_id_4 AND status = \'rejected\') AS rejected_books,
                (SELECT COUNT(*) FROM books WHERE seller_id = :seller_id_5 AND status = \'revision_requested\') AS revision_requested,
                (SELECT COALESCE(SUM(stock_quantity), 0) FROM books WHERE seller_id = :seller_id_6 AND status = \'approved\') AS available_stock,
                (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
                 WHERE oi.seller_id = :seller_id_7 AND o.order_status <> \'cancelled\') AS total_orders,
                (SELECT COUNT(DISTINCT oi.order_id) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
                 WHERE oi.seller_id = :seller_id_8 AND o.order_status = \'delivered\') AS completed_sales,
                (SELECT COALESCE(SUM(oi.line_total), 0) FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
                 WHERE oi.seller_id = :seller_id_9 AND o.order_status <> \'cancelled\') AS gross_sales,
                (SELECT COALESCE(SUM(sc.seller_net_amount), 0) FROM seller_commissions sc
                 WHERE sc.seller_id = :seller_id_10 AND sc.status IN (\'pending\', \'available\')) AS estimated_earnings,
                (SELECT COUNT(*) FROM seller_notifications WHERE seller_id = :seller_id_11 AND is_read = 0) AS unread_notifications'
        );
        $parameters = [];
        for ($index = 1; $index <= 11; $index++) {
            $parameters['seller_id_' . $index] = $sellerId;
        }
        $statement->execute($parameters);

        return $statement->fetch() ?: [];
    }

    public function recentNotifications(int $sellerId, int $limit = 5): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, message, notification_type, is_read, created_at
             FROM seller_notifications WHERE seller_id = :seller_id
             ORDER BY created_at DESC LIMIT :result_limit'
        );
        $statement->bindValue(':seller_id', $sellerId, PDO::PARAM_INT);
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
