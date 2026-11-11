<?php
// Genel bölüm: çağrı bandı (başlık + yazı + düğme)
return [
    'name'        => 'Çağrı bandı',
    'description' => 'Dikkat çeken bant: "Hemen başlayın", "Teklif alın" gibi.',
    'icon'        => 'megaphone',
    'fields'      => [
        'title'        => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Projenizi konuşalım'],
        'text'         => ['label' => 'Yazı', 'type' => 'textarea', 'default' => 'Bir telefon ya da e-posta uzağınızdayız.'],
        'button_text'  => ['label' => 'Düğme yazısı', 'type' => 'text', 'default' => 'İletişime geçin'],
        'button_link'  => ['label' => 'Düğme bağlantısı', 'type' => 'link', 'default' => '#iletisim'],
        'style'        => ['label' => 'Görünüm', 'type' => 'select', 'default' => 'primary', 'options' => ['primary' => 'Ana renk zemin', 'muted' => 'Açık zemin', 'outline' => 'Çerçeveli kart']],
    ],
];
