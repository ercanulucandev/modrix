-- Duyarlı görseller: farklı genişlikte WebP kopyaları {"640": "media/...-640w.webp", "1024": ...}
ALTER TABLE media ADD sizes TEXT NULL AFTER thumb_path;
