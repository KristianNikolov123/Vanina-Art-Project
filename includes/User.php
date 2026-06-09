<?php

class User
{
    public int $id;
    public string $username;
    public string $email;
    public string $password_hash;
    public bool $is_verified;

    public function __construct(int $id, string $username, string $email, string $password_hash, bool $is_verified = false)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->password_hash = $password_hash;
        $this->is_verified = $is_verified;
    }

    public static function get(int $userId): ?User
    {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if ($user) {
            return new User($user['id'], $user['username'], $user['email'], $user['password_hash'], (bool)$user['is_verified']);
        }
        return null;
    }

    public static function getByEmail(string $email): ?User
    {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            return new User($user['id'], $user['username'], $user['email'], $user['password_hash'], (bool)$user['is_verified']);
        }
        return null;
    }

    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public static function create(string $username, string $email, string $password): bool
    {
        try {
            $conn = get_db_connection();
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, is_verified) VALUES (?, ?, ?, 0)');
            $stmt->execute([$username, $email, $passwordHash]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function verifyUser(string $email): bool
    {
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare('UPDATE users SET is_verified = 1 WHERE email = ?');
            $stmt->execute([$email]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function logLogin(int $userId, string $username): bool
    {
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare('INSERT INTO login_logs (user_id, username) VALUES (?, ?)');
            $stmt->execute([$userId, $username]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function getLoginLogs(int $limit = 50): array
    {
        $conn = get_db_connection();
        $limit = max(1, $limit);
        $stmt = $conn->prepare("
            SELECT l.*, u.email
            FROM login_logs l
            JOIN users u ON l.user_id = u.id
            ORDER BY l.login_time DESC
            LIMIT {$limit}
        ");
        $stmt->execute();
        $logs = $stmt->fetchAll();

        $tz = new DateTimeZone('Europe/Sofia');
        foreach ($logs as &$log) {
            if (!empty($log['login_time'])) {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $log['login_time'], new DateTimeZone('UTC'));
                if ($dt) {
                    $dt->setTimezone($tz);
                    $log['login_time'] = $dt->format('d.m.Y H:i');
                }
            }
        }
        return $logs;
    }

    public static function isAdminEmail(string $email): bool
    {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT * FROM admin_emails WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }
}
