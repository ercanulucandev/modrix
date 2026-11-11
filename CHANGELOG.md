# Sürüm notları

Her sürüm "## vX.Y.Z" başlığıyla; sürüm yayınlanınca bu bölüm GitHub'daki sürüm notu ve sitelerdeki Güncellemeler sayfasında gösterilir.

## v1.0.0

İlk sürüm: sade çekirdek ve başlangıç teması (Default). Modüller ve temalar mağazadan kurulur
([modrix-store](https://github.com/ercanulucandev/modrix-store), [modrix-themes](https://github.com/ercanulucandev/modrix-themes)).

### Yönetim paneli
- Sade menü (gruplar ve sekmeler), genel bakış (30 günlük grafik, dikkat isteyenler, hızlı işlemler), gece modu
- Medya kütüphanesi: WebP dönüştürme, görsel düzenleyici, duyarlı görseller (srcset)
- Canlı tema özelleştirme, sürükle-bırak ana sayfa bölümleri, menüler

### Altyapı
- Modül sistemi, koleksiyonlar (hazır liste/form altyapısı), hook'lar, bölümler, ayarlar
- Kullanıcılar, roller ve yetkiler, iki adımlı doğrulama, işlem kaydı, SMTP e-posta, şifre sıfırlama
- Çoklu dil: `/en/...` adresleri, içerik ve ayar çevirileri, arayüz metinleri, hreflang
- SEO: site haritası, robots.txt, yönlendirmeler, 404 kaydı, yapılandırılmış veri
- Sayfa önbelleği, içerik geçmişi, site içi arama

### Bakım
- Mağaza: modül ve temaları tek tıkla kur/güncelle (SHA-256 doğrulama)
- Tek tıkla güncelleme: önce yedek, hata olursa dosya ve veritabanı geri alınır
- Yedekler, kurulum sihirbazı (`/install`), komut satırı (`php modrix`), testler
