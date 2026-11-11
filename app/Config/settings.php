<?php
// Admin > Ayarlar sayfasının sekmeleri ve alanları. Değerler 'settings' tablosunda tutulur.
// Kullanım: Setting::get('site_name'), Setting::bool('maintenance_mode'), Setting::url('logo')
//
// Alan türleri: text, textarea, email, url, tel, color, checkbox, select (options), image, code
// Modüller kendi sekmelerini ekleyebilir:
//   Hook::on('admin.settings', fn(array $g) => [...$g, 'blog' => ['label' => 'Blog', 'fields' => [...]]]);
return [
    'general' => [
        'label' => 'Genel',
        'description' => 'Sitenin adı ve temel bilgileri.',
        'fields' => [
            'site_name'        => ['label' => 'Site adı', 'type' => 'text', 'rules' => 'required|max:100', 'default' => 'Modrix'],
            'site_tagline'     => ['label' => 'Slogan', 'type' => 'text', 'rules' => 'nullable|max:150', 'help' => 'Ana sayfada başlığın altında görünür.'],
            'site_description' => ['label' => 'Kısa tanıtım', 'type' => 'textarea', 'rules' => 'nullable|max:500'],
            'footer_text'      => ['label' => 'Alt bilgi metni', 'type' => 'text', 'rules' => 'nullable|max:200', 'help' => 'Boşsa "© Yıl Site adı" yazılır.'],
        ],
    ],

    'branding' => [
        'label' => 'Logo ve simge',
        'description' => 'Sitede ve tarayıcı sekmesinde görünen görseller.',
        'fields' => [
            'logo'    => ['label' => 'Logo', 'type' => 'image', 'help' => 'Medya kütüphanesinden seçin. Yoksa site adı yazılır.'],
            'favicon' => ['label' => 'Favicon', 'type' => 'image', 'help' => 'Kare görsel; PNG ya da ICO, 32×32 ya da büyüğü.'],
            'og_image'=> ['label' => 'Paylaşım görseli', 'type' => 'image', 'help' => 'Sosyal medyada link paylaşılınca görünür. 1200×630 önerilir.'],
        ],
    ],

    'media' => [
        'label' => 'Medya',
        'description' => 'Yüklenen görsellerin otomatik işlenmesi. Sadece bundan sonraki yüklemeleri etkiler; GIF ve ICO olduğu gibi kalır.',
        'fields' => [
            'media_convert_webp'  => ['label' => 'Görselleri WebP\'ye çevir', 'type' => 'checkbox', 'default' => '1',
                                      'help' => 'JPG ve PNG dosyaları genelde %25–80 daha küçük WebP olarak kaydedilir. Sonuç daha büyük çıkarsa orijinal korunur.'],
            'media_quality'       => ['label' => 'Kalite', 'type' => 'number', 'rules' => 'required|integer|between:40,100', 'default' => '82',
                                      'help' => 'JPEG ve WebP için 40–100. 80–85 çoğu site için iyi bir denge; düştükçe dosya küçülür.'],
            'media_max_dimension' => ['label' => 'En uzun kenar (piksel)', 'type' => 'number', 'rules' => 'required|integer|between:0,10000', 'default' => '2560',
                                      'help' => 'Daha büyük görseller oranı korunarak bu boyuta küçültülür. 0 = küçültme yapma.'],
            'media_strip_exif'    => ['label' => 'Fotoğraf bilgilerini (EXIF) sil', 'type' => 'checkbox', 'default' => '1',
                                      'help' => 'Konum (GPS), kamera ve çekim tarihi gibi bilgiler dosyadan silinir. Gizlilik için önerilir.'],
            'media_auto_orient'   => ['label' => 'Yönü otomatik düzelt', 'type' => 'checkbox', 'default' => '1',
                                      'help' => 'Telefonla çekilip yan görünen fotoğraflar doğru yöne çevrilir.'],
            'media_max_size'      => ['label' => 'Dosya başına en fazla (MB)', 'type' => 'number', 'rules' => 'required|integer|between:1,100', 'default' => '10'],
            'media_thumb_width'   => ['label' => 'Küçük resim genişliği (piksel)', 'type' => 'number', 'rules' => 'required|integer|between:100,1200', 'default' => '400',
                                      'help' => 'Kütüphane ızgarasında gösterilen önizleme.'],
        ],
    ],

    'seo' => [
        'label' => 'SEO',
        'description' => 'Arama motorları ve sosyal medya için.',
        'fields' => [
            'title_format'     => ['label' => 'Sayfa başlığı biçimi', 'type' => 'text', 'rules' => 'required|max:100', 'default' => '{title} · {site}',
                                   'help' => '{title} sayfa başlığı, {site} site adı ile değişir.'],
            'meta_description' => ['label' => 'Meta açıklama', 'type' => 'textarea', 'rules' => 'nullable|max:300', 'help' => 'Google sonuçlarında görünen açıklama. 150–160 karakter ideal.'],
            'meta_keywords'    => ['label' => 'Anahtar kelimeler', 'type' => 'text', 'rules' => 'nullable|max:300', 'help' => 'Virgülle ayırın.'],
            'robots_noindex'   => ['label' => 'Arama motorlarından gizle', 'type' => 'checkbox', 'help' => 'Açıkken site Google\'da listelenmez (geliştirme aşaması için); robots.txt her şeyi kapatır.'],
            'schema_type'      => ['label' => 'Site kimdir', 'type' => 'select', 'rules' => 'required|in:Organization,LocalBusiness,Person', 'default' => 'Organization',
                                   'options' => ['Organization' => 'Şirket / kurum', 'LocalBusiness' => 'Yerel işletme (adresli)', 'Person' => 'Kişi (kişisel site)'],
                                   'help' => 'Google\'a site sahibi olarak bildirilir (ad, logo, iletişim ve sosyal medya bilgileriyle).'],
            'robots_extra'     => ['label' => 'robots.txt ek kuralları', 'type' => 'code', 'rules' => 'nullable|max:2000',
                                   'help' => 'Örn. "Disallow: /ozel-klasor". Site haritası satırı kendiliğinden eklenir: /robots.txt, /sitemap.xml'],
            'google_analytics' => ['label' => 'Google Analytics ölçüm kimliği', 'type' => 'text', 'translatable' => false, 'rules' => 'nullable|regex:/^G-[A-Z0-9]{4,20}$/', 'placeholder' => 'G-XXXXXXXXXX'],
        ],
    ],

    'contact' => [
        'label' => 'İletişim',
        'description' => 'Alt bilgide ve iletişim alanlarında gösterilir.',
        'fields' => [
            'contact_email'   => ['label' => 'E-posta', 'type' => 'email', 'rules' => 'nullable|email|max:190'],
            'contact_phone'   => ['label' => 'Telefon', 'type' => 'tel', 'rules' => 'nullable|phone'],
            'contact_address' => ['label' => 'Adres', 'type' => 'textarea', 'rules' => 'nullable|max:300'],
            'social_instagram'=> ['label' => 'Instagram', 'type' => 'url', 'rules' => 'nullable|url|max:255', 'placeholder' => 'https://instagram.com/...'],
            'social_x'        => ['label' => 'X (Twitter)', 'type' => 'url', 'rules' => 'nullable|url|max:255', 'placeholder' => 'https://x.com/...'],
            'social_facebook' => ['label' => 'Facebook', 'type' => 'url', 'rules' => 'nullable|url|max:255'],
            'social_linkedin' => ['label' => 'LinkedIn', 'type' => 'url', 'rules' => 'nullable|url|max:255'],
            'social_youtube'  => ['label' => 'YouTube', 'type' => 'url', 'rules' => 'nullable|url|max:255'],
        ],
    ],

    'appearance' => [
        'label' => 'Panel görünümü',
        'description' => 'Yönetim panelinin rengi ve köşeleri. Sitenin tasarımı temadan gelir: Görünüm > Özelleştir.',
        'fields' => [
            'primary_color' => ['label' => 'Ana renk', 'type' => 'color', 'rules' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'default' => '#18181b',
                                'help' => 'Butonlar ve vurgular. Yazı rengi otomatik seçilir.'],
            'radius'        => ['label' => 'Köşeler', 'type' => 'select', 'rules' => 'required|in:none,small,medium,large', 'default' => 'small',
                                'options' => ['none' => 'Keskin (0)', 'small' => 'Hafif yuvarlak', 'medium' => 'Yuvarlak', 'large' => 'Daha yuvarlak']],
        ],
    ],

    'mail' => [
        'translatable' => false,
        'label' => 'E-posta',
        'description' => 'Form bildirimleri ve şifre sıfırlama e-postaları buradan gönderilir. Hosting e-postanızın ya da Gmail/Yandex gibi bir hizmetin SMTP bilgilerini girin; kaydettikten sonra aşağıdan deneme e-postası gönderin.',
        'fields' => [
            'mail_driver'       => ['label' => 'Gönderim yolu', 'type' => 'select', 'rules' => 'required|in:smtp,mail,log', 'default' => 'mail',
                                    'options' => ['smtp' => 'SMTP sunucusu (önerilen)', 'mail' => 'PHP mail() (sunucu ayarlıysa)', 'log' => 'Gönderme, log dosyasına yaz (geliştirme)']],
            'mail_host'         => ['label' => 'SMTP sunucusu', 'type' => 'text', 'rules' => 'nullable|max:190', 'placeholder' => 'smtp.gmail.com'],
            'mail_port'         => ['label' => 'Port', 'type' => 'number', 'rules' => 'nullable|integer|between:1,65535', 'default' => '587', 'help' => '587 (STARTTLS) ya da 465 (SSL).'],
            'mail_encryption'   => ['label' => 'Şifreleme', 'type' => 'select', 'rules' => 'required|in:tls,ssl,none', 'default' => 'tls',
                                    'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'Yok (önerilmez)']],
            'mail_username'     => ['label' => 'Kullanıcı adı', 'type' => 'text', 'rules' => 'nullable|max:190', 'placeholder' => 'ornek@siteniz.com'],
            'mail_password'     => ['label' => 'Şifre', 'type' => 'password', 'rules' => 'nullable|max:190',
                                    'help' => 'Kayıtlı şifreyi değiştirmeyecekseniz boş bırakın. Gmail için "uygulama şifresi" kullanın.'],
            'mail_from_address' => ['label' => 'Gönderen adresi', 'type' => 'email', 'rules' => 'nullable|email|max:190', 'help' => 'Sitenizin alan adından bir adres (ör. bilgi@siteniz.com); yoksa e-postalar spam\'e düşebilir.'],
            'mail_from_name'    => ['label' => 'Gönderen adı', 'type' => 'text', 'rules' => 'nullable|max:100', 'help' => 'Boşsa site adı.'],
        ],
    ],

    'performance' => [
        'translatable' => false,
        'label' => 'Performans',
        'description' => 'Ziyaretçilere sayfaları hazır kopyadan vererek siteyi hızlandırır.',
        'fields' => [
            'cache_enabled' => ['label' => 'Sayfa önbelleği', 'type' => 'checkbox',
                                'help' => 'Giriş yapmamış ziyaretçiler sayfaların hazır kopyasını görür. Panelde her kayıtta önbellek kendiliğinden temizlenir.'],
            'cache_ttl'     => ['label' => 'Önbellek süresi (dakika)', 'type' => 'number', 'rules' => 'required|integer|between:1,10080', 'default' => '60',
                                'help' => 'Bu süreden eski kopyalar yenilenir. Zamanlanmış yazılar en geç bu sürede görünür.'],
        ],
    ],

    'security' => [
        'translatable' => false,
        'label' => 'Güvenlik',
        'description' => 'Panel girişi ve kayıtlar.',
        'fields' => [
            'two_factor_required' => ['label' => 'İki adımlı doğrulama zorunlu', 'type' => 'checkbox',
                                      'help' => 'Açıkken her panel kullanıcısı girişte Profilim > Güvenlik\'te doğrulayıcı uygulamayı kurmadan devam edemez. Önce kendinizinkini açın.'],
            'activity_days'       => ['label' => 'İşlem kaydı saklama (gün)', 'type' => 'number', 'rules' => 'required|integer|between:7,3650', 'default' => '180'],
            'backup_keep'         => ['label' => 'Saklanacak yedek sayısı', 'type' => 'number', 'rules' => 'required|integer|between:1,100', 'default' => '10',
                                      'help' => 'Yeni yedek alınınca bundan eski olanlar silinir.'],
        ],
    ],

    'maintenance' => [
        'label' => 'Bakım modu',
        'description' => 'Açıkken ziyaretçiler bakım sayfası görür. Admin girişi yapmış olan siz siteyi normal görürsünüz.',
        'fields' => [
            'maintenance_mode'    => ['label' => 'Bakım modunu aç', 'type' => 'checkbox'],
            'maintenance_message' => ['label' => 'Bakım mesajı', 'type' => 'textarea', 'rules' => 'nullable|max:500',
                                      'default' => 'Sitemiz kısa bir süreliğine bakımda. Lütfen daha sonra tekrar deneyin.'],
        ],
    ],

    'code' => [
        'translatable' => false,
        'label' => 'Özel kod',
        'description' => 'Tüm sayfalara eklenen HTML/JS (ör. canlı destek, piksel). Sadece güvendiğiniz kodu ekleyin.',
        'fields' => [
            'head_code'   => ['label' => '<head> içine', 'type' => 'code', 'rules' => 'nullable|max:10000'],
            'footer_code' => ['label' => '</body> öncesine', 'type' => 'code', 'rules' => 'nullable|max:10000'],
        ],
    ],
];
