<?php
// Yerel ayarları değiştirmek için app/Config/database.local.php oluştur (git'e girmez):
//   <?php return ['name' => 'baska_db', 'pass' => 'sifre'];
$defaults = [
    'host'    => '127.0.0.1',
    'port'    => 3306,
    'name'    => 'modrix',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
];

$local = __DIR__ . '/database.local.php';
return is_file($local) ? array_merge($defaults, require $local) : $defaults;
