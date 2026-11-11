# Güncellemeler

Sürüm: `Core\App::VERSION` (`php modrix version`). Yeni sürümler GitHub deposunun **Releases** bölümünden gelir
(`app/Config/app.php` → `update.repository`). Site sahibi **Admin > Sistem > Güncellemeler**'de sürüm notlarını görür,
tek tıkla günceller. Yeni sürüm varsa menüde rozet ve Genel bakış'ta uyarı çıkar (12 saatte bir denetlenir).

## Güncelleme ne yapar?

1. Paketi indirir, varsa `.sha256` özetiyle doğrular, ZIP'i güvenli açar (`../` ile dışarı yazılamaz), sürümünü okur.
2. **Tam yedek** alır (veritabanı + dosyalar, Admin > Yedekler'de görünür).
3. Siteyi bakım moduna alır, paketteki dosyaları kopyalar; değişen her dosyanın eski halini saklar.
4. Yeni migration'ları çalıştırır.
5. Bir adımda hata olursa: dosyalar eski haline döner, eklenen dosyalar silinir, **veritabanı yedekten geri yüklenir**,
   yarım kalan migration'ın açtığı tablolar kaldırılır. Bakım modu her durumda eski haline döner.

**Asla dokunulmayanlar:** `storage/` (yüklenenler, yedekler, loglar), `app/Config/*.local.php` (veritabanı, şifreleme
anahtarı), `.git`. Pakette olmayan dosyalar silinmez (kendi tema ve modülleriniz kalır). **Hazır tema ve modüllerin
dosyalarında değişiklik yaptıysanız güncellemede ezilir:** değişiklikleri kendi temanıza/modülünüze taşıyın.

Güncellemeden sonra modüllerin yeni sürümleri varsa Modüller sayfasında "Güncelle" ile migration'ları çalıştırılır.

Komut satırı: `php modrix update:check`, `php modrix update`, yerel paketten `php modrix update --file=modrix-1.2.0.zip`.

## Gizli depo

Depo gizliyse Güncellemeler sayfasına GitHub erişim anahtarı girin: GitHub > Settings > Developer settings >
Fine-grained tokens > yalnız bu depo, **Contents: Read-only**. Anahtar veritabanında şifreli durur.

## Yeni sürüm yayınlamak (geliştirici)

1. `core/App.php` içinde `VERSION`'ı artırın (ör. `1.1.0`).
2. `CHANGELOG.md`'nin başına `## v1.1.0` bölümü ekleyip değişiklikleri yazın (sürüm notu olarak gösterilir).
3. Commit ve push, ardından etiketi gönderin:

```bash
git tag -a v1.1.0 -m "Modrix v1.1.0"
```

```bash
git push origin v1.1.0
```

GitHub Actions (`.github/workflows/release.yml`) gerisini yapar: etiketin `VERSION` ile aynı olduğunu denetler, testleri
çalıştırır, `modrix-1.1.0.zip` + `.sha256` paketini üretir ve sürümü yayınlar. Siteler 12 saat içinde "yeni sürüm var" görür.
Elle yapmak gerekirse: `php modrix release:pack` ve `gh release create`.
