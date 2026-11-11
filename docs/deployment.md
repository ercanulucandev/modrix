# Canlıya alma

## Kontrol listesi

- [ ] `app/Config/app.php` → `'debug' => false` (hata ayrıntıları gösterilmez, sadece log'a yazılır)
- [ ] Veritabanı bilgileri `app/Config/database.local.php` içinde (git'e girmez)
- [ ] `php modrix migrate` çalıştı, modüller kurulu (`php modrix module:list`)
- [ ] Güçlü şifreli admin hesabı (`php modrix admin:create ...`); test hesapları silindi
- [ ] `app/Config/key.local.php` yeni sunucuya kopyalandı (2FA anahtarları); yedek için cron: `php modrix backup`
- [ ] HTTPS sertifikası var (`.htaccess` HTTP'yi HTTPS'e yönlendirir; çerezler `secure`)
- [ ] `storage/` yazılabilir; `storage/uploads` ve `storage/logs` yedekleniyor
- [ ] Ayarlar > SEO > "Arama motorlarından gizle" **kapalı**
- [ ] Ayarlar > Bakım modu kapalı (açıkken site sadece adminlere görünür)
- [ ] Formlar > Ayarlar: bildirim ve gönderen adresi; sunucuda e-posta gönderimi çalışıyor (deneme mesajı gönderin)
- [ ] PHP eklentileri: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `zip`

## Dosyaları yükleme

Gönderilmeyecekler: `.git/`, `dist/`, `storage/logs/*`, `storage/tmp/*`, `app/Config/*.local.php` (sunucuda ayrıca oluşturun).
`storage/uploads/` içeriği taşınacaksa ayrıca kopyalanır (medya kütüphanesi ve ayar görselleri).

## Apache

Proje kökündeki `.htaccess` hazırdır: HTTPS yönlendirmesi, kod/veri klasörlerinin (`app`, `core`, `storage`...) kapatılması,
gizli dosyaların reddi ve her isteğin `index.php`'ye gitmesi. `AllowOverride All` ve `mod_rewrite` gerekir.

## Nginx

`.htaccess` çalışmaz; aynı kuralları sunucu ayarına yazın:

```nginx
server {
    listen 443 ssl;
    server_name siteniz.com;
    root /var/www/siteniz;
    index index.php;

    # Kod ve veri klasörleri, gizli dosyalar: uygulamaya (404)
    location ~ ^/(app|core|storage|vendor|dist|docs)(/|$) { rewrite ^ /index.php last; }
    location ~ ^/(composer\.(json|lock)|modrix)$ { rewrite ^ /index.php last; }
    location ~ /\. { rewrite ^ /index.php last; }

    location / { try_files $uri /index.php?$query_string; }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~ \.php$ { return 404; }   # index.php dışındaki PHP dosyaları çalışmaz
}
```

## Güncelleme

1. Yedek alın (veritabanı + `storage/uploads`).
2. Dosyaları yükleyin (`app/Config/*.local.php` ve `storage/` üzerine yazmadan).
3. `php modrix migrate` (çekirdek) ve değişen modüller için Admin > Modüller > **Güncelle** (ya da `php modrix module:update Ad`).
4. Tarayıcıda Görünüm > Özelleştir ve birkaç sayfayı kontrol edin; `storage/logs/` içinde yeni hata var mı bakın.
