<?php
declare(strict_types=1);

namespace Bookstore\Services;

use PDO;

final class BookCatalogue
{
    public static function find(PDO $pdo, array $filters, int $limit, int $offset): array
    {
        [$conditions, $parameters] = self::conditions($filters);
        $sort = self::sortClause((string) ($filters['sort'] ?? 'newest'));

        $sql = 'SELECT b.id, b.title, b.slug, b.description, b.price, b.discount_price,
                       b.stock_quantity, b.cover_image, c.name AS category_name,
                       p.name AS publisher_name,
                       GROUP_CONCAT(a.name ORDER BY ba.author_order SEPARATOR \', \') AS authors,
                       COALESCE(rating_stats.average_rating, 0) AS average_rating,
                       COALESCE(rating_stats.review_count, 0) AS review_count,
                       COALESCE(sales_stats.units_sold, 0) AS units_sold
                FROM books b
                LEFT JOIN categories c ON c.id = b.category_id
                LEFT JOIN publishers p ON p.id = b.publisher_id
                LEFT JOIN book_authors ba ON ba.book_id = b.id
                LEFT JOIN authors a ON a.id = ba.author_id
                LEFT JOIN (
                    SELECT book_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                    FROM reviews
                    WHERE status = \'approved\'
                    GROUP BY book_id
                ) rating_stats ON rating_stats.book_id = b.id
                LEFT JOIN (
                    SELECT oi.book_id, SUM(oi.quantity) AS units_sold
                    FROM order_items oi
                    INNER JOIN orders o ON o.id = oi.order_id AND o.order_status <> \'cancelled\'
                    GROUP BY oi.book_id
                ) sales_stats ON sales_stats.book_id = b.id
                WHERE ' . implode(' AND ', $conditions) . '
                GROUP BY b.id, c.name, p.name, rating_stats.average_rating,
                         rating_stats.review_count, sales_stats.units_sold
                ORDER BY ' . $sort . ', b.title ASC
                LIMIT :result_limit OFFSET :result_offset';

        $statement = $pdo->prepare($sql);
        self::bindParameters($statement, $parameters);
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':result_offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function count(PDO $pdo, array $filters): int
    {
        [$conditions, $parameters] = self::conditions($filters);
        $sql = 'SELECT COUNT(DISTINCT b.id)
                FROM books b
                LEFT JOIN categories c ON c.id = b.category_id
                LEFT JOIN (
                    SELECT book_id, AVG(rating) AS average_rating
                    FROM reviews
                    WHERE status = \'approved\'
                    GROUP BY book_id
                ) rating_stats ON rating_stats.book_id = b.id
                WHERE ' . implode(' AND ', $conditions);
        $statement = $pdo->prepare($sql);
        self::bindParameters($statement, $parameters);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public static function findBySlug(PDO $pdo, string $slug): ?array
    {
        $statement = $pdo->prepare(
            'SELECT b.*, c.name AS category_name, p.name AS publisher_name,
                    GROUP_CONCAT(a.name ORDER BY ba.author_order SEPARATOR \', \') AS authors,
                    COALESCE(rating_stats.average_rating, 0) AS average_rating,
                    COALESCE(rating_stats.review_count, 0) AS review_count
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN publishers p ON p.id = b.publisher_id
             LEFT JOIN book_authors ba ON ba.book_id = b.id
             LEFT JOIN authors a ON a.id = ba.author_id
             LEFT JOIN (
                 SELECT book_id, AVG(rating) AS average_rating, COUNT(*) AS review_count
                 FROM reviews
                 WHERE status = \'approved\'
                 GROUP BY book_id
             ) rating_stats ON rating_stats.book_id = b.id
             WHERE b.slug = :slug AND b.status = \'active\'
             GROUP BY b.id, c.name, p.name, rating_stats.average_rating, rating_stats.review_count
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $book = $statement->fetch();

        return $book === false ? null : $book;
    }

    public static function categories(PDO $pdo): array
    {
        $statement = $pdo->query(
            'SELECT c.name, c.slug, COUNT(b.id) AS book_count
             FROM categories c
             LEFT JOIN books b ON b.category_id = c.id AND b.status = \'active\'
             WHERE c.is_active = 1
             GROUP BY c.id, c.name, c.slug
             ORDER BY c.name'
        );

        return $statement->fetchAll();
    }

    public static function publishers(PDO $pdo): array
    {
        $statement = $pdo->query(
            'SELECT DISTINCT p.id, p.name
             FROM publishers p
             INNER JOIN books b ON b.publisher_id = p.id AND b.status = \'active\'
             ORDER BY p.name'
        );

        return $statement->fetchAll();
    }

    public static function bestSellers(PDO $pdo, int $limit): array
    {
        $statement = $pdo->prepare(
            'SELECT b.id, b.title, b.slug, b.price, b.discount_price,
                    GROUP_CONCAT(a.name ORDER BY ba.author_order SEPARATOR \', \') AS authors,
                    sales_stats.units_sold
             FROM books b
             INNER JOIN (
                 SELECT oi.book_id, SUM(oi.quantity) AS units_sold
                 FROM order_items oi
                 INNER JOIN orders o ON o.id = oi.order_id AND o.order_status <> \'cancelled\'
                 GROUP BY oi.book_id
             ) sales_stats ON sales_stats.book_id = b.id
             LEFT JOIN book_authors ba ON ba.book_id = b.id
             LEFT JOIN authors a ON a.id = ba.author_id
             WHERE b.status = \'active\'
             GROUP BY b.id, sales_stats.units_sold
             ORDER BY sales_stats.units_sold DESC, b.created_at DESC
             LIMIT :result_limit'
        );
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function approvedReviews(PDO $pdo, int $bookId, int $limit = 10): array
    {
        $statement = $pdo->prepare(
            'SELECT r.rating, r.review_text, r.created_at, u.first_name, u.last_name
             FROM reviews r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.book_id = :book_id AND r.status = \'approved\'
             ORDER BY r.created_at DESC
             LIMIT :result_limit'
        );
        $statement->bindValue(':book_id', $bookId, PDO::PARAM_INT);
        $statement->bindValue(':result_limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private static function conditions(array $filters): array
    {
        $conditions = ["b.status = 'active'"];
        $parameters = [];
        $search = (string) ($filters['search'] ?? '');

        if ($search !== '') {
            $conditions[] = "(b.title LIKE :title_search OR b.isbn LIKE :isbn_search OR EXISTS (
                SELECT 1
                FROM book_authors search_link
                INNER JOIN authors search_author ON search_author.id = search_link.author_id
                WHERE search_link.book_id = b.id AND search_author.name LIKE :author_search
            ))";
            $pattern = '%' . $search . '%';
            $parameters['title_search'] = $pattern;
            $parameters['isbn_search'] = $pattern;
            $parameters['author_search'] = $pattern;
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'c.slug = :category_slug';
            $parameters['category_slug'] = $filters['category'];
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'b.category_id = :category_id';
            $parameters['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['exclude_id'])) {
            $conditions[] = 'b.id <> :exclude_id';
            $parameters['exclude_id'] = (int) $filters['exclude_id'];
        }

        if (!empty($filters['publisher_id'])) {
            $conditions[] = 'b.publisher_id = :publisher_id';
            $parameters['publisher_id'] = (int) $filters['publisher_id'];
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $conditions[] = 'COALESCE(b.discount_price, b.price) >= :min_price';
            $parameters['min_price'] = $filters['min_price'];
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $conditions[] = 'COALESCE(b.discount_price, b.price) <= :max_price';
            $parameters['max_price'] = $filters['max_price'];
        }

        if (isset($filters['min_rating']) && $filters['min_rating'] !== '') {
            $conditions[] = 'COALESCE(rating_stats.average_rating, 0) >= :min_rating';
            $parameters['min_rating'] = $filters['min_rating'];
        }

        return [$conditions, $parameters];
    }

    private static function sortClause(string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'COALESCE(b.discount_price, b.price) ASC',
            'price_desc' => 'COALESCE(b.discount_price, b.price) DESC',
            'popular' => 'COALESCE(sales_stats.units_sold, 0) DESC',
            default => 'b.created_at DESC',
        };
    }

    private static function bindParameters(\PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $statement->bindValue(':' . $name, $value, $type);
        }
    }
}
