-- Bağlantının bitiş zamanı (davet bağlantıları sıfırlama bağlantılarından uzun geçerli)
ALTER TABLE password_resets ADD expires_at DATETIME NULL AFTER created_at;
