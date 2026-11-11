<?php
// Görünüm > Özelleştir alanları. Biçim app/Config/settings.php ile aynı; bölümler sekme olur.
// Okuma: Theme::get('hero_title'), Theme::bool('sticky_header'), Theme::url('hero_image'), Theme::color('primary_color')
return [
    'colors' => [
        'label'  => 'Renk ve yazı',
        'fields' => [
            'primary_color' => ['label' => 'Ana renk', 'type' => 'color', 'rules' => 'required|regex:/^#[0-9a-fA-F]{6}$/', 'default' => '#18181b',
                                'help' => 'Düğmeler, bağlantılar ve vurgular.'],
            'radius'        => ['label' => 'Köşeler', 'type' => 'select', 'rules' => 'required|in:none,small,medium,large', 'default' => 'small',
                                'options' => ['none' => 'Keskin', 'small' => 'Hafif yuvarlak', 'medium' => 'Yuvarlak', 'large' => 'Çok yuvarlak']],
            'font'          => ['label' => 'Yazı tipi', 'type' => 'select', 'rules' => 'required|in:inter,manrope,dmsans,lora', 'default' => 'inter',
                                'options' => ['inter' => 'Inter (sade)', 'manrope' => 'Manrope (modern)', 'dmsans' => 'DM Sans (yumuşak)', 'lora' => 'Lora (tırnaklı, blog)']],
        ],
    ],
    'header' => [
        'label'  => 'Üst kısım',
        'fields' => [
            'header_search'   => ['label' => 'Arama simgesi', 'type' => 'checkbox', 'default' => '1', 'help' => 'Menünün yanında site içi arama (/search).'],
            'sticky_header'   => ['label' => 'Kaydırınca üstte sabit kalsın', 'type' => 'checkbox', 'default' => '1'],
            'header_cta_text' => ['label' => 'Düğme yazısı', 'type' => 'text', 'rules' => 'nullable|max:40', 'help' => 'Menünün sağında düğme. Boşsa gösterilmez.'],
            'header_cta_url'  => ['label' => 'Düğme bağlantısı', 'type' => 'text', 'rules' => 'nullable|max:255', 'placeholder' => 'iletisim ya da https://...'],
        ],
    ],
    'footer' => [
        'label'  => 'Alt kısım',
        'fields' => [
            'footer_about'   => ['label' => 'Tanıtım yazısı', 'type' => 'textarea', 'rules' => 'nullable|max:300', 'help' => 'Boşsa Ayarlar > Genel\'deki kısa tanıtım.'],
            'footer_contact' => ['label' => 'İletişim bilgilerini göster', 'type' => 'checkbox', 'default' => '1', 'help' => 'Ayarlar > İletişim\'den gelir.'],
            'footer_social'  => ['label' => 'Sosyal medya bağlantılarını göster', 'type' => 'checkbox', 'default' => '1'],
        ],
    ],
];
