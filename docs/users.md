# Kullanıcılar, roller ve yetkiler

**Admin > Kullanıcılar**: panele girebilen kişiler. **Roller** sekmesi: her rolün yapabildikleri.

- **Yönetici** (sistem rolü): her şey. Silinemez, yetkileri değişmez. Her zaman en az bir aktif Yönetici kalır.
- **Editör** (hazır): medya, sayfalar, blog yazıları, formlar. İstediğiniz gibi değiştirin.
- Yeni rol: ad + işaretlenen yetkiler (ör. "Mağaza sorumlusu": sadece e-ticaret ve medya).
- **Site üyesi** (`user`): panele giremez (ileride üyelik modülleri için).

Kullanıcı eklerken şifre boş bırakılırsa kişiye **davet e-postası** gider (3 gün geçerli, şifresini kendisi belirler).
Herkes kendi adını, e-postasını ve şifresini **Profilim**'den değiştirir (sol alttaki adına tıklayarak).
Kendi rolünüzü/durumunuzu değiştiremez, kendinizi silemezsiniz.

## Yetkiler

| Yetki | Açar |
|---|---|
| `media.manage` | Medya sayfası, görsel düzenleme, silme (kütüphaneden seçme ve yükleme herkese açık) |
| `appearance.manage` | Temalar, Özelleştir, Menüler |
| `modules.manage` | Modüller ve modül ayarları |
| `settings.manage` | Site ayarları |
| `users.manage` | Kullanıcılar ve roller |
| `pages.manage`, `blog.manage`, `forms.manage` | Modüllerin kendi yetkileri |

Yetkisi olmayan kullanıcı ilgili menüyü görmez; adresi elle yazarsa **403** sayfası çıkar.

## Modülde yetki tanımlamak

```json
// module.json
"permissions": { "siparis.manage": "Siparişleri yönet", "siparis.export": "Siparişleri dışa aktar" },
"menu": { "admin": [{ "url": "admin/siparis", "label": "Siparişler", "icon": "package", "permission": "siparis.manage" }] }
```

```php
// routes.php
Router::group(['prefix' => '/admin/siparis', 'middleware' => ['admin', 'can:siparis.manage']], function () { ... });
```

```php
// Kod içinde
if (Access::can('siparis.export')) { ... }           // App\Services\Access
```

- Yetki adları `modul.is` biçiminde (küçük harf, nokta). Modül aktif olunca Roller ekranında kendi grubunda listelenir.
- Genel bakış kutularına ve hook menü öğelerine de `'permission' => 'siparis.manage'` ekleyin; yetkisi olmayan görmez.
- Yetki listesine kod ile eklemek: `Hook::on('admin.permissions', fn($groups) => [...$groups, 'Grup' => ['x.y' => 'Açıklama']])`.
