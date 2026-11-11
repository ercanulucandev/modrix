-- İçerik geçmişi: yazı/sayfa her kaydedildiğinde bir sürüm (Düzenleyici > Geçmiş)
CREATE TABLE revisions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type       VARCHAR(30) NOT NULL,          -- 'post', 'page' (Revision::register)
    item_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    user_name  VARCHAR(100) NULL,
    title      VARCHAR(255) NULL,
    data       MEDIUMTEXT NOT NULL,           -- JSON: kaydedilen alanlar
    hash       CHAR(40) NOT NULL,             -- aynı içerik iki kez saklanmasın
    created_at DATETIME NOT NULL,
    INDEX (type, item_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
