<?php

function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $driver = $_ENV['DB_DRIVER'] ?? 'sqlite';

        if ($driver === 'mysql') {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'vanina_art';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? '';
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass);
        } else {
            $dbPath = ROOT_PATH . '/database.db';
            $pdo = new PDO('sqlite:' . $dbPath);
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function init_db(): void
{
    if (($_ENV['DB_DRIVER'] ?? 'sqlite') === 'mysql') {
        return;
    }

    $conn = get_db_connection();

    $conn->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            is_verified BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS login_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS admin_emails (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            is_active BOOLEAN DEFAULT 1
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS glasses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS passepartouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            price REAL NOT NULL,
            stock INTEGER NOT NULL
        )
    ');

    $conn->exec('
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            sub_order_number INTEGER DEFAULT 0,
            date TEXT NOT NULL,
            width REAL,
            height REAL,
            profile TEXT,
            additional_profiles TEXT,
            frame_count INTEGER DEFAULT 1,
            description TEXT,
            glass TEXT,
            back TEXT,
            passepartout TEXT,
            hanging TEXT,
            price REAL,
            advance_payment REAL,
            discount REAL,
            customer_name TEXT,
            paid BOOLEAN DEFAULT 0,
            collected BOOLEAN DEFAULT 0
        )
    ');
}
