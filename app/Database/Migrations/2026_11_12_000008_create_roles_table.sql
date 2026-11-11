-- Yönetim paneli rolleri ve yetkileri. users.role bu tablodaki slug'dır ('user' gibi tabloda olmayan roller panele giremez).
CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL,
    slug        VARCHAR(40) NOT NULL UNIQUE,
    permissions TEXT NOT NULL,                   -- JSON: ["pages.manage", "media.manage"] ya da ["*"] (her şey)
    is_system   TINYINT(1) NOT NULL DEFAULT 0,   -- silinemez
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, permissions, is_system, created_at, updated_at) VALUES
    ('Yönetici', 'admin', '["*"]', 1, NOW(), NOW()),
    ('Editör', 'editor', '["media.manage","pages.manage","blog.manage","forms.manage"]', 0, NOW(), NOW());
