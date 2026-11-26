<?php
declare(strict_types=1);

namespace Bookstore\Services;

use PDO;
use PDOException;
use RuntimeException;

final class Authentication
{
    private const RATE_WINDOW_MINUTES = 15;
    private const MAX_EMAIL_LOGIN_FAILURES = 5;
    private const MAX_IP_LOGIN_FAILURES = 20;
    private const MAX_IP_REGISTRATIONS = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public function isRateLimited(string $email, string $action): bool
    {
        if (!in_array($action, ['login', 'registration'], true)) {
            throw new \InvalidArgumentException('Unsupported authentication action.');
        }

        $statement = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(action = \'login\' AND email_hash = :email_hash AND was_successful = 0), 0) AS email_failures,
                COALESCE(SUM(action = \'login\' AND ip_address = :login_ip AND was_successful = 0), 0) AS ip_login_failures,
                COALESCE(SUM(action = \'registration\' AND ip_address = :registration_ip), 0) AS ip_registrations
             FROM auth_attempts
             WHERE attempted_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL ' . self::RATE_WINDOW_MINUTES . ' MINUTE)'
        );
        $ipAddress = $this->clientIpAddress();
        $statement->bindValue(':email_hash', $this->emailHash($email), PDO::PARAM_STR);
        $statement->bindValue(':login_ip', $ipAddress, PDO::PARAM_STR);
        $statement->bindValue(':registration_ip', $ipAddress, PDO::PARAM_STR);
        $statement->execute();
        $counts = $statement->fetch();

        if ($action === 'registration') {
            return (int) $counts['ip_registrations'] >= self::MAX_IP_REGISTRATIONS;
        }

        return (int) $counts['email_failures'] >= self::MAX_EMAIL_LOGIN_FAILURES
            || (int) $counts['ip_login_failures'] >= self::MAX_IP_LOGIN_FAILURES;
    }

    public function recordAttempt(string $email, string $action, bool $successful): void
    {
        if (!in_array($action, ['login', 'registration'], true)) {
            throw new \InvalidArgumentException('Unsupported authentication action.');
        }

        $cleanup = $this->pdo->prepare(
            'DELETE FROM auth_attempts
             WHERE attempted_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)'
        );
        $cleanup->execute();

        $statement = $this->pdo->prepare(
            'INSERT INTO auth_attempts (email_hash, ip_address, action, was_successful)
             VALUES (:email_hash, :ip_address, :action, :was_successful)'
        );
        $statement->bindValue(':email_hash', $this->emailHash($email), PDO::PARAM_STR);
        $statement->bindValue(':ip_address', $this->clientIpAddress(), PDO::PARAM_STR);
        $statement->bindValue(':action', $action, PDO::PARAM_STR);
        $statement->bindValue(':was_successful', $successful ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();
    }

    public function authenticate(string $email, string $password): ?array
    {
        if (strlen($password) > 72) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email, role, status, session_version, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user === false || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $update = $this->pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :user_id');
        $update->execute(['user_id' => (int) $user['id']]);
        unset($user['password_hash'], $user['status']);

        return $user;
    }

    public function register(string $firstName, string $lastName, string $email, ?string $phone, string $password): array
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
                 VALUES (\'customer\', :first_name, :last_name, :email, :phone, :password_hash, \'active\')'
            );
            $statement->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => $passwordHash,
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $wishlist = $this->pdo->prepare(
                'INSERT INTO wishlists (user_id, name) VALUES (:user_id, \'My wishlist\')'
            );
            $wishlist->execute(['user_id' => $userId]);

            $this->recordAttempt($email, 'registration', true);
            $this->pdo->commit();

            return [
                'id' => $userId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => 'customer',
                'session_version' => 0,
            ];
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function registerSeller(array $data): int
    {
        $passwordHash = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $applicationData = json_encode($data['application_data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->pdo->beginTransaction();
        try {
            $user = $this->pdo->prepare(
                'INSERT INTO users (role, first_name, last_name, email, phone, password_hash, status)
                 VALUES (\'seller\', :first_name, :last_name, :email, :phone, :password_hash, \'active\')'
            );
            $user->execute([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password_hash' => $passwordHash,
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $seller = $this->pdo->prepare(
                'INSERT INTO sellers
                    (user_id, store_name, seller_type, description, business_logo, phone, region, city, business_address)
                 VALUES
                    (:user_id, :store_name, :seller_type, :description, :business_logo, :phone, :region, :city, :business_address)'
            );
            $seller->execute([
                'user_id' => $userId,
                'store_name' => $data['store_name'],
                'seller_type' => $data['seller_type'],
                'description' => $data['description'],
                'business_logo' => $data['business_logo'],
                'phone' => $data['phone'],
                'region' => $data['region'],
                'city' => $data['city'],
                'business_address' => $data['business_address'],
            ]);
            $sellerId = (int) $this->pdo->lastInsertId();

            $application = $this->pdo->prepare(
                'INSERT INTO seller_applications (seller_id, application_data, status)
                 VALUES (:seller_id, :application_data, \'pending\')'
            );
            $application->execute([
                'seller_id' => $sellerId,
                'application_data' => $applicationData,
            ]);

            $notification = $this->pdo->prepare(
                'INSERT INTO seller_notifications (seller_id, title, message, notification_type)
                 VALUES (:seller_id, :title, :message, \'application_received\')'
            );
            $notification->execute([
                'seller_id' => $sellerId,
                'title' => 'Application received',
                'message' => 'Your seller application has been received and is waiting for administrator review.',
            ]);

            $activity = $this->pdo->prepare(
                'INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
                 VALUES (:user_id, \'seller.application_submitted\', \'seller\', :seller_id, :details)'
            );
            $activity->execute([
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'details' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
            ]);

            $this->recordAttempt((string) $data['email'], 'registration', true);
            $this->pdo->commit();

            return $sellerId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)), true);
    }

    private function clientIpAddress(): string
    {
        $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipAddress = is_string($remoteAddress) ? inet_pton($remoteAddress) : false;

        return $ipAddress === false ? str_repeat("\0", 16) : $ipAddress;
    }
}
