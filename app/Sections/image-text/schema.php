<?php
// Genel bölüm: yan yana görsel ve yazı
return [
    'name'        => 'Görsel ve yazı',
    'description' => 'Bir yanda görsel, diğer yanda başlık, yazı ve düğme.',
    'icon'        => 'columns',
    'fields'      => [
        'image'       => ['label' => 'Görsel', 'type' => 'image'],
        'title'       => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Biz kimiz?'],
        'text'        => ['label' => 'Yazı', 'type' => 'textarea', 'default' => "Kısa bir tanıtım yazısı.\nİkinci paragraf için yeni satıra geçin."],
        'button_text' => ['label' => 'Düğme yazısı', 'type' => 'text', 'default' => ''],
        'button_link' => ['label' => 'Düğme bağlantısı', 'type' => 'link', 'default' => ''],
        'side'        => ['label' => 'Görselin yeri', 'type' => 'select', 'default' => 'left', 'options' => ['left' => 'Solda', 'right' => 'Sağda']],
        'background'  => ['label' => 'Zemin', 'type' => 'select', 'default' => 'none', 'options' => ['none' => 'Yok', 'muted' => 'Açık gri']],
    ],
];
