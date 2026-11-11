<?php
return [
    'name'        => 'Giriş',
    'description' => 'Sade başlık, açıklama, düğme ve isteğe bağlı görsel.',
    'icon'        => 'sparkles',
    'fields'      => [
        'title'       => ['label' => 'Başlık', 'type' => 'text', 'default' => '', 'help' => 'Boşsa site adı.'],
        'text'        => ['label' => 'Açıklama', 'type' => 'textarea', 'default' => '', 'help' => 'Boşsa Ayarlar > Genel\'deki slogan ve tanıtım.'],
        'button_text' => ['label' => 'Düğme yazısı', 'type' => 'text', 'default' => ''],
        'button_link' => ['label' => 'Düğme bağlantısı', 'type' => 'link', 'default' => ''],
        'image'       => ['label' => 'Görsel', 'type' => 'image', 'help' => 'Başlığın yanında. Boşsa sadece yazı.'],
    ],
];
