-- Şifre sıfırlama bağlantıları (Admin > Şifremi unuttum). Anahtarın kendisi değil SHA-256 özeti saklanır.
CREATE TABLE password_resets (
    email      VARCHAR(190) PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
