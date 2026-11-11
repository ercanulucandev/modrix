<?php
// Genel bölüm: başlık + zengin metin (her temada)
return [
    'name'        => 'Yazı bloğu',
    'description' => 'Başlık ve biçimli yazı: tanıtım, duyuru, açıklama.',
    'icon'        => 'text',
    'fields'      => [
        'title'      => ['label' => 'Başlık', 'type' => 'text', 'default' => 'Başlık'],
        'content'    => ['label' => 'İçerik', 'type' => 'richtext', 'default' => '<p>Buraya yazınızı ekleyin. Kalın, italik, liste, bağlantı ve görsel kullanabilirsiniz.</p>'],
        'align'      => ['label' => 'Hizalama', 'type' => 'select', 'default' => 'left', 'options' => ['left' => 'Sola', 'center' => 'Ortaya']],
        'width'      => ['label' => 'Genişlik', 'type' => 'select', 'default' => 'narrow', 'options' => ['narrow' => 'Dar (okunaklı)', 'wide' => 'Geniş']],
        'background' => ['label' => 'Zemin', 'type' => 'select', 'default' => 'none', 'options' => ['none' => 'Yok', 'muted' => 'Açık gri']],
    ],
];
