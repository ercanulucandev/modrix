# E-posta

Ayarlar: **Admin > Ayarlar > E-posta**. Kaydettikten sonra aynı sayfadan **deneme e-postası** gönderin; hata olursa sebebi
(ör. "Kullanıcı adı ya da şifre hatalı") ekranda yazar.

| Gönderim yolu | Ne zaman |
|---|---|
| **SMTP sunucusu** (önerilen) | Hosting e-postası, Gmail, Yandex, Outlook, Mailgun, Brevo... |
| PHP mail() | Sunucuda sendmail ayarlıysa (çoğu hostingde spam'e düşer) |
| Log dosyasına yaz | Geliştirme: e-posta gönderilmez, `storage/logs/mail.log` dosyasına yazılır |

Sık kullanılan SMTP ayarları:

| Hizmet | Sunucu | Port / şifreleme | Not |
|---|---|---|---|
| Gmail / Google Workspace | `smtp.gmail.com` | 587 STARTTLS | Hesap şifresi değil **uygulama şifresi** (2 adımlı doğrulama açık olmalı) |
| Yandex | `smtp.yandex.com` | 465 SSL | Uygulama şifresi |
| Outlook / Microsoft 365 | `smtp.office365.com` | 587 STARTTLS | |
| cPanel hosting | `mail.alanadiniz.com` | 465 SSL | e-posta hesabının tam adresi ve şifresi |

**Gönderen adresi** sitenizin alan adından olmalıdır (`bilgi@siteniz.com`); başka alan adından gönderilen e-postalar spam'e düşer.

## Koddan gönderme

```php
Mail::send('ali@site.com', 'Siparişiniz alındı', "Merhaba Ali,\n\nSiparişiniz hazırlanıyor.");

Mail::send(['a@x.com', 'b@x.com'], 'Konu', 'Düz metin sürümü', [
    'html'     => '<p>HTML sürümü</p>',      // isteğe bağlı
    'reply_to' => 'musteri@x.com',          // "Yanıtla" bu adrese
]);

if (!Mail::send(...)) {
    $neden = Mail::lastError();             // hata ayrıca log'a yazılır
}
```

- Konu ve adlardaki satır sonları silinir, adresler doğrulanır (başlık enjeksiyonuna karşı).
- Kullanıcıdan gelen değerleri HTML sürümüne `View::e()` ile koyun.
- Form bildirimleri ve şifre sıfırlama bu ayarları kullanır.

## Şifre sıfırlama

Giriş ekranındaki **Şifremi unuttum** → e-postaya 60 dakika geçerli, tek kullanımlık bağlantı. E-postanın kayıtlı olup
olmadığı belli edilmez, aynı adrese dakikada en fazla bir e-posta gider, anahtarın sadece özeti saklanır.
