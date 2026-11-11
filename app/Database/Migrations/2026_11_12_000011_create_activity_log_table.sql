-- İşlem kaydı (Admin > Kullanıcılar > İşlem kaydı): kim, ne zaman, ne yaptı
CREATE TABLE activity_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,                 -- yapan (başarısız girişte null)
    user_name    VARCHAR(100) NULL,                 -- kullanıcı silinse de kayıtta adı kalsın
    action       VARCHAR(40) NOT NULL,              -- 'auth.login', 'post.updated', 'settings.updated'...
    description  VARCHAR(500) NOT NULL,
    subject_url  VARCHAR(255) NULL,                 -- ilgili kayda bağlantı (admin içi)
    ip           VARCHAR(45) NULL,
    created_at   DATETIME NOT NULL,
    INDEX (created_at),
    INDEX (user_id, created_at),
    INDEX (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
