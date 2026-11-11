-- Kurulu modüller. Sadece status = 'active' olanların route'ları ve menüleri yüklenir.
CREATE TABLE modules (
    name         VARCHAR(100) PRIMARY KEY,
    version      VARCHAR(20) NOT NULL,
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    installed_at DATETIME NULL,
    updated_at   DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
