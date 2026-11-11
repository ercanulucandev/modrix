-- Bölüm düzenleri (Görünüm > Özelleştir) JSON olarak saklanır; TEXT (64 KB) yetmeyebilir
ALTER TABLE settings MODIFY value MEDIUMTEXT NULL;
