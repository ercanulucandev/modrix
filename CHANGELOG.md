# Sürüm notları

Her sürüm "## vX.Y.Z" başlığıyla; sürüm yayınlanınca bu bölüm GitHub'daki sürüm notu ve sitelerdeki Güncellemeler sayfasında gösterilir.

## v1.0.2

- Kurulum: veritabanı ayarı henüz yazılmamış yeni bir kopya, aynı sunucuda varsayılan adla (`modrix`) duran başka bir sitenin
  veritabanını görüp kendini kurulu sanmaz; kurulum sihirbazı açılır

## v1.0.1

### Temalar
- Yeni başlangıç teması **Başlangıç** (eski adı Default): simgeli özellik kartları bölümü eklendi
- Mağazada iki yeni tema: **Blueberry** (yazılım / SaaS) ve **Redmo** (şirket / ajans); Kurumsal ve Lacivert'in yerini aldı
- Etkin olmayan temalar **Görünüm**'den silinebilir (ayarları ve menü atamaları da silinir)
- Modül sayfalarında kullanılan durum rozetleri ve uyarı kutusu tüm temalarda ortak

### Düzeltmeler
- Sürüm ZIP'inden kurulumda `storage/` ve `storage/uploads` olmadığı için çıkan hata: çalışma klasörleri artık kendiliğinden oluşturulur
- Kaldırılmış bir modülü mağazadan yeniden kurarken çıkan "klasör zaten var" hatası
- Modül deposu `modrix-modules` olarak yeniden adlandırıldı; `store:build` çıktısı yandaki depo klasörüne yazılır

## v1.0.0

İlk sürüm: sade çekirdek ve başlangıç teması. Modüller ve temalar mağazadan kurulur
([modrix-modules](https://github.com/ercanulucandev/modrix-modules), [modrix-themes](https://github.com/ercanulucandev/modrix-themes)).

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
