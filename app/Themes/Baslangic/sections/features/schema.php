<?php
// Başlangıç teması: simgeli özellik kartları
return [
    'name'        => 'Özellikler',
    'description' => 'Simgeli kısa kartlar: hizmetler, avantajlar, adımlar.',
    'icon'        => 'grid',
    'fields'      => [
        'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Neden biz?'],
        'text'  => ['label' => 'Açıklama', 'type' => 'text', 'default' => 'İşinizi kolaylaştıran birkaç neden.'],
        'items' => ['label' => 'Kartlar', 'type' => 'repeater', 'item_name' => 'Kart', 'item_label' => 'title', 'max' => 12,
            'fields' => [
                'icon'  => ['label' => 'Simge', 'type' => 'icon', 'default' => 'bolt'],
                'title' => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Yeni kart'],
                'text'  => ['label' => 'Açıklama', 'type' => 'textarea', 'default' => ''],
            ],
            'default' => [
                ['icon' => 'bolt', 'title' => 'Hızlı', 'text' => 'Sayfalar hafif ve hızlı açılır; ziyaretçi beklemez.'],
                ['icon' => 'shield', 'title' => 'Güvenli', 'text' => 'Güncel altyapı, yedekler ve iki adımlı doğrulama.'],
                ['icon' => 'heart', 'title' => 'Kolay', 'text' => 'Her şeyi sade bir panelden kendiniz yönetirsiniz.'],
            ]],
    ],
];
