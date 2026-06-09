-- Run in phpMyAdmin or: mysql -u root vanina_art < database/migration_002_pricing.sql

USE vanina_art;

CREATE TABLE IF NOT EXISTS passepartout_sheet_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    width_cm INT UNSIGNED NOT NULL,
    height_cm INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_sheet_types_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passepartout_cut_sizes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sheet_type_id INT UNSIGNED NOT NULL,
    width_cm INT UNSIGNED NOT NULL,
    height_cm INT UNSIGNED NOT NULL,
    CONSTRAINT fk_cut_sizes_sheet
        FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_cut_per_sheet (sheet_type_id, width_cm, height_cm)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passepartout_sheet_availability (
    passepartout_id INT UNSIGNED NOT NULL,
    sheet_type_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (passepartout_id, sheet_type_id),
    CONSTRAINT fk_avail_passepartout
        FOREIGN KEY (passepartout_id) REFERENCES passepartouts (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_avail_sheet
        FOREIGN KEY (sheet_type_id) REFERENCES passepartout_sheet_types (id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS passepartout_bill_width DECIMAL(10, 2) NULL AFTER passepartout,
    ADD COLUMN IF NOT EXISTS passepartout_bill_height DECIMAL(10, 2) NULL AFTER passepartout_bill_width;

ALTER TABLE profiles MODIFY stock DECIMAL(10, 2) NOT NULL DEFAULT 0;
ALTER TABLE glasses MODIFY stock DECIMAL(10, 2) NOT NULL DEFAULT 0;

INSERT IGNORE INTO passepartout_sheet_types (name, width_cm, height_cm) VALUES
    ('80x120', 80, 120),
    ('80x100', 80, 100);

INSERT IGNORE INTO passepartout_cut_sizes (sheet_type_id, width_cm, height_cm)
SELECT st.id, cuts.width_cm, cuts.height_cm
FROM passepartout_sheet_types st
JOIN (
    SELECT '80x120' AS sheet_name, 60 AS width_cm, 80 AS height_cm UNION ALL
    SELECT '80x120', 40, 60 UNION ALL
    SELECT '80x120', 30, 40 UNION ALL
    SELECT '80x100', 50, 80 UNION ALL
    SELECT '80x100', 40, 50 UNION ALL
    SELECT '80x100', 25, 40
) cuts ON cuts.sheet_name = st.name;
