-- İki adımlı doğrulama (TOTP): şifreli gizli anahtar, kurtarma kodlarının özetleri, son kullanılan zaman adımı (tekrar kullanımı önler)
ALTER TABLE users
    ADD two_factor_secret       TEXT NULL AFTER password,
    ADD two_factor_recovery     TEXT NULL AFTER two_factor_secret,
    ADD two_factor_last_step    BIGINT UNSIGNED NULL AFTER two_factor_recovery,
    ADD two_factor_confirmed_at DATETIME NULL AFTER two_factor_last_step;
