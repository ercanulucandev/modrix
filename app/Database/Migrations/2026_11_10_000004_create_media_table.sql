-- Medya kütüphanesi. Dosyalar storage/uploads/media/ altında, /media/... adresinden sunulur.
CREATE TABLE media (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path        VARCHAR(255) NOT NULL,          -- media/2026/11/3f9a....webp
    thumb_path  VARCHAR(255) NULL,              -- görseller için küçük resim
    name        VARCHAR(255) NOT NULL,          -- orijinal dosya adı
    title       VARCHAR(255) NULL,
    alt         VARCHAR(255) NULL,              -- görsel açıklaması (erişilebilirlik / SEO)
    mime        VARCHAR(100) NOT NULL,
    type        ENUM('image','document') NOT NULL,
    size        INT UNSIGNED NOT NULL,
    width       INT UNSIGNED NULL,
    height      INT UNSIGNED NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    UNIQUE (path),
    INDEX (type, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
