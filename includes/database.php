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

    if ($isMysql) {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_stock (
                passepartout_id INT UNSIGNED NOT NULL,
                sheet_type_id INT UNSIGNED NOT NULL,
                stock DECIMAL(10, 4) NOT NULL DEFAULT 0,
                PRIMARY KEY (passepartout_id, sheet_type_id),
                CONSTRAINT fk_pp_stock_passepartout
                    FOREIGN KEY (passepartout_id) REFERENCES passepartouts (id) ON DELETE CASCADE,
                CONSTRAINT fk_pp_stock_sheet
                    FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ');
    } else {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS passepartout_sheet_stock (
                passepartout_id INTEGER NOT NULL,
                sheet_type_id INTEGER NOT NULL,
                stock REAL NOT NULL DEFAULT 0,
                PRIMARY KEY (passepartout_id, sheet_type_id),
                FOREIGN KEY (passepartout_id) REFERENCES passepartouts (id) ON DELETE CASCADE,
                FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id) ON DELETE CASCADE
            )
        ');
    }

    $orderColumns = [
        'passepartout_bill_width' => $isMysql ? 'DECIMAL(10,2) NULL' : 'REAL',
        'passepartout_bill_height' => $isMysql ? 'DECIMAL(10,2) NULL' : 'REAL',
        'passepartout_sheet_type_id' => $isMysql ? 'INT UNSIGNED NULL' : 'INTEGER',
        'passepartout_sheet_usage' => $isMysql ? 'DECIMAL(10,4) NULL' : 'REAL',
    ];
    foreach ($orderColumns as $column => $type) {
        if (!table_has_column($conn, 'orders', $column)) {
            $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$type}");
        }
    }

    seed_passepartout_sheet_catalog($conn);
    ensure_oversize_passepartout_cuts($conn);
    migrate_legacy_passepartout_stock($conn);
    ensure_catalog_schema($conn);
}

function ensure_catalog_schema(PDO $conn): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    if ($isMysql) {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS backs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                min_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
                stock DECIMAL(10, 2) NOT NULL DEFAULT 0,
                UNIQUE KEY uq_backs_name (name)
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS hanging_options (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                min_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
                pricing_unit VARCHAR(20) NOT NULL DEFAULT "piece",
                stock INT NOT NULL DEFAULT 0,
                UNIQUE KEY uq_hanging_name (name)
            ) ENGINE=InnoDB
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS pricing_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value DECIMAL(10, 4) NOT NULL,
                label VARCHAR(255) NOT NULL,
                category VARCHAR(50) NOT NULL DEFAULT "general"
            ) ENGINE=InnoDB
        ');
    } else {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS backs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                price REAL NOT NULL,
                min_price REAL NOT NULL DEFAULT 0,
                stock REAL NOT NULL DEFAULT 0
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS hanging_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                price REAL NOT NULL,
                min_price REAL NOT NULL DEFAULT 0,
                pricing_unit TEXT NOT NULL DEFAULT "piece",
                stock INTEGER NOT NULL DEFAULT 0
            )
        ');
        $conn->exec('
            CREATE TABLE IF NOT EXISTS pricing_settings (
                setting_key TEXT PRIMARY KEY,
                setting_value REAL NOT NULL,
                label TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT "general"
            )
        ');
    }

    if (!table_has_column($conn, 'glasses', 'min_price')) {
        $type = $isMysql ? 'DECIMAL(10,2) NOT NULL DEFAULT 0' : 'REAL NOT NULL DEFAULT 0';
        $conn->exec("ALTER TABLE glasses ADD COLUMN min_price {$type}");
    }

    if (!table_has_column($conn, 'profiles', 'width_cm')) {
        $type = $isMysql ? 'DECIMAL(10,2) NULL' : 'REAL';
        $conn->exec("ALTER TABLE profiles ADD COLUMN width_cm {$type}");
    }

    if (!table_has_column($conn, 'profiles', 'profile_type')) {
        $type = $isMysql ? "VARCHAR(20) NOT NULL DEFAULT 'wood'" : "TEXT NOT NULL DEFAULT 'wood'";
        $conn->exec("ALTER TABLE profiles ADD COLUMN profile_type {$type}");
    }

    if ($isMysql) {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                min_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
                pricing_unit VARCHAR(20) NOT NULL DEFAULT "piece",
                category VARCHAR(50) NOT NULL DEFAULT "general",
                UNIQUE KEY uq_services_name (name)
            ) ENGINE=InnoDB
        ');
    } else {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                price REAL NOT NULL,
                min_price REAL NOT NULL DEFAULT 0,
                pricing_unit TEXT NOT NULL DEFAULT "piece",
                category TEXT NOT NULL DEFAULT "general"
            )
        ');
    }

    $orderExtraColumns = [
        'passepartout_openings' => $isMysql ? 'INT UNSIGNED NOT NULL DEFAULT 1' : 'INTEGER NOT NULL DEFAULT 1',
        'urgent' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'student_discount' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'complex_passepartout' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'extra_services' => $isMysql ? 'TEXT NULL' : 'TEXT',
        'transport_km' => $isMysql ? 'DECIMAL(10,2) NULL' : 'REAL',
        'frame_box' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'frame_nonstandard' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'frame_shape' => $isMysql ? "VARCHAR(20) NOT NULL DEFAULT ''" : "TEXT NOT NULL DEFAULT ''",
        'frame_high_complexity' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'client_passepartout_cutting' => $isMysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'deleted_at' => $isMysql ? 'DATETIME NULL' : 'TEXT',
    ];
    foreach ($orderExtraColumns as $column => $type) {
        if (!table_has_column($conn, 'orders', $column)) {
            $conn->exec("ALTER TABLE orders ADD COLUMN {$column} {$type}");
        }
    }

    seed_price_list_catalog($conn);
    ensure_passepartout_tier_pricing_schema($conn);
}

function ensure_passepartout_tier_pricing_schema(PDO $conn): void
{
    $isMysql = $conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $columns = [
        'price_kind' => $isMysql ? "VARCHAR(20) NOT NULL DEFAULT 'manual'" : "TEXT NOT NULL DEFAULT 'manual'",
        'tier_scheme' => $isMysql ? "VARCHAR(10) NOT NULL DEFAULT '80x100'" : "TEXT NOT NULL DEFAULT '80x100'",
        'price_tier_1' => $isMysql ? 'DECIMAL(10,2) NOT NULL DEFAULT 0' : 'REAL NOT NULL DEFAULT 0',
        'price_tier_2' => $isMysql ? 'DECIMAL(10,2) NOT NULL DEFAULT 0' : 'REAL NOT NULL DEFAULT 0',
        'price_tier_3' => $isMysql ? 'DECIMAL(10,2) NOT NULL DEFAULT 0' : 'REAL NOT NULL DEFAULT 0',
        'price_tier_4' => $isMysql ? 'DECIMAL(10,2) NOT NULL DEFAULT 0' : 'REAL NOT NULL DEFAULT 0',
    ];

    foreach ($columns as $column => $type) {
        if (!table_has_column($conn, 'passepartouts', $column)) {
            $conn->exec("ALTER TABLE passepartouts ADD COLUMN {$column} {$type}");
        }
    }
}

function migrate_legacy_passepartout_stock(PDO $conn): void
{
    if (!table_has_column($conn, 'passepartouts', 'stock')) {
        return;
    }

    $rows = $conn->query('SELECT id, stock FROM passepartouts WHERE stock > 0')->fetchAll();
    foreach ($rows as $row) {
        $passepartoutId = (int)$row['id'];
        $existing = get_passepartout_sheet_stocks($conn, $passepartoutId);
        if (!empty($existing)) {
            continue;
        }

        $sheetTypeIds = get_passepartout_sheet_type_ids($conn, $passepartoutId);
        if (empty($sheetTypeIds)) {
            continue;
        }

        save_passepartout_sheet_stocks($conn, $passepartoutId, [
            $sheetTypeIds[0] => (float)$row['stock'],
        ]);
    }
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
