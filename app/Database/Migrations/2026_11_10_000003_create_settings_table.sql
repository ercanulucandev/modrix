-- Site ayarları (anahtar / değer). Alanların tanımı: app/Config/settings.php
CREATE TABLE settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    value      TEXT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
