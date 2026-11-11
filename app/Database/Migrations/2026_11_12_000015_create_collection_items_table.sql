-- Koleksiyonlar: modüllerin basit içerik listeleri (SSS, ekip, referanslar, portfolyo...). Alanlar data (JSON) içinde.
CREATE TABLE collection_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection  VARCHAR(40) NOT NULL,          -- 'faq', 'team', 'portfolio'... (Collection::register)
    title       VARCHAR(255) NOT NULL,
    slug        VARCHAR(190) NULL,             -- kendi sayfası olan koleksiyonlarda
    data        MEDIUMTEXT NOT NULL,
    visible     TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    INDEX (collection, visible, sort_order),
    UNIQUE (collection, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
