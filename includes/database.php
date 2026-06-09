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

function ensure_pricing_schema(PDO $conn): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    if ($isMysql) {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                width_cm INT UNSIGNED NOT NULL,
                height_cm INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_sheet_types_name (name)
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_cut_sizes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sheet_type_id INT UNSIGNED NOT NULL,
                width_cm INT UNSIGNED NOT NULL,
                height_cm INT UNSIGNED NOT NULL,
                UNIQUE KEY uq_cut_per_sheet (sheet_type_id, width_cm, height_cm),
                CONSTRAINT fk_cut_sizes_sheet
                    FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_availability (
                passepartout_id INT UNSIGNED NOT NULL,
                sheet_type_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (passepartout_id, sheet_type_id),
                CONSTRAINT fk_avail_passepartout
                    FOREIGN KEY (passepartout_id) REFERENCES passepartouts (id) ON DELETE CASCADE,
                CONSTRAINT fk_avail_sheet
                    FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');
    } else {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                width_cm INTEGER NOT NULL,
                height_cm INTEGER NOT NULL
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_cut_sizes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sheet_type_id INTEGER NOT NULL,
                width_cm INTEGER NOT NULL,
                height_cm INTEGER NOT NULL,
                FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE,
                UNIQUE(sheet_type_id, width_cm, height_cm)
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_availability (
                passepartout_id INTEGER NOT NULL,
                sheet_type_id INTEGER NOT NULL,
                PRIMARY KEY (passepartout_id, sheet_type_id),
                FOREIGN KEY (passepartout_id) REFERENCES passepartouts (id) ON DELETE CASCADE,
                FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE
            )
        ');
    }

    $orderColumns = ['passepartout_bill_width', 'passepartout_bill_height'];
    foreach ($orderColumns as $column) {
        if (!table_has_column($conn, 'orders', $column)) {
            $type = $isMysql ? 'DECIMAL(10,2) NULL' : 'REAL';
            $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$type}");
        }
    }

    seed_passepartout_sheet_catalog($conn);
}

function table_has_column(PDO $conn, string $table, string $column): bool
{
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $stmt = $conn->prepare('
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ');
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $conn->query("PRAGMA table_info({$table})");
    while ($row = $stmt->fetch()) {
        if (($row['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function init_db(): void
{
    $conn = get_db_connection();

    if (($_ENV['DB_DRIVER'] ?? 'sqlite') === 'mysql') {
        ensure_pricing_schema($conn);
        return;
    }

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
            collected BOOLEAN DEFAULT 0,
            passepartout_bill_width REAL,
            passepartout_bill_height REAL
        )
    ');

    ensure_pricing_schema($conn);
}
