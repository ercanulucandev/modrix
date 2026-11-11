-- SEO: yönlendirmeler (Admin > SEO) ve bulunamayan adres kayıtları (404)
CREATE TABLE redirects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source      VARCHAR(500) NOT NULL,              -- 'eski-sayfa' ya da 'eski-klasor/*' (sonu * ise altındakiler de)
    target      VARCHAR(500) NOT NULL,              -- 'yeni-sayfa', 'yeni-klasor/*', 'https://...'
    code        SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    hits        INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    is_auto     TINYINT(1) NOT NULL DEFAULT 0,      -- sayfa/yazı adresi değişince otomatik eklendi
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    UNIQUE (source(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE not_found_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path       VARCHAR(500) NOT NULL,
    hits       INT UNSIGNED NOT NULL DEFAULT 1,
    referer    VARCHAR(500) NULL,                   -- son gelinen sayfa (kırık bağlantının yeri)
    first_seen DATETIME NOT NULL,
    last_seen  DATETIME NOT NULL,
    UNIQUE (path(190)),
    INDEX (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
