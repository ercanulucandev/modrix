-- Arayüz metinlerinin çevirileri (Admin > Sistem > Diller > Arayüz metinleri). Kaynak metin koddaki Türkçe: Lang::t('...')
CREATE TABLE translations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang        CHAR(2) NOT NULL,
    source      VARCHAR(500) NOT NULL,
    translation VARCHAR(1000) NOT NULL,
    updated_at  DATETIME NULL,
    UNIQUE (lang, source(190))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
