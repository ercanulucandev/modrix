-- Menüler (Görünüm > Menüler). Hangi menünün nerede görüneceği temanın menü konumlarına atanarak seçilir
-- (ayar: menu_location_{tema}_{konum} = menü id).
CREATE TABLE menus (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id    INT UNSIGNED NOT NULL,
    parent_id  INT UNSIGNED NULL,               -- alt menü için üst öğe
    label      VARCHAR(100) NOT NULL,
    url        VARCHAR(500) NOT NULL,           -- 'blog', '/hakkimizda', 'https://...', '#iletisim', 'tel:...'
    new_tab    TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX (menu_id, parent_id, sort_order),
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
