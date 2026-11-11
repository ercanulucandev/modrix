# Testler

```bash
php modrix test              # hepsi
php modrix test Security     # adında "Security" geçenler
```

Bağımlılık yok (PHPUnit gerekmez): `tests/` altındaki `*Test.php` sınıfları, `test` ile başlayan metotlar.
Veritabanı testleri bir işlem (transaction) içinde çalışır ve sonunda geri alınır; veritabanı yoksa atlanır (S).

```php
<?php
namespace Tests;

final class SiparisTest extends TestCase
{
    public function testToplam(): void
    {
        $this->assertSame(3000, App\Modules\Siparis\Services\Sepet::toplam([1000, 2000]));
        $this->assertThrows(\Core\HttpException::class, fn() => App\Modules\Siparis\Services\Sepet::ekle(0));
    }

    public function testVeritabani(): void
    {
        $this->needsDatabase();
        // ... (işlem sonunda geri alınır)
    }
}
```

Doğrulamalar: `assertTrue`, `assertFalse`, `assertNull`, `assertSame`, `assertEquals`, `assertContains`, `assertNotContains`,
`assertCount`, `assertThrows`. Özel metot: `self::call(Sinif::class, 'metot', ...$arg)`.

## GitHub Actions

`.github/workflows/tests.yml`: her push ve pull request'te PHP 8.1 ve 8.4 ile söz dizimi denetimi, temiz bir MySQL'e
tüm migration'lar ve modül kurulumları, ardından `php modrix test`. Sonuç GitHub'da depo > **Actions** sekmesinde.

Mevcut testler: TOTP (RFC 6238 vektörleri), şifreleme, HTML temizleyici (XSS), doğrulayıcı, rota eşleşmesi, dil önekli
adresler, modül manifestleri ve `.down.sql`'ler, kuruş hesabı, güncelleyicinin korunan dosyaları, sorgu oluşturucu
(kaçırma, sayfalama, koşulsuz silme engeli), iç içe işlemler.
