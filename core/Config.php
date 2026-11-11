<?php
namespace Core;

// app/Config/*.php dosyalarını okur. Dosya adı ilk anahtardır:
//   Config::get('app.debug')         -> app/Config/app.php içindeki 'debug'
//   Config::get('database.host')     -> app/Config/database.php içindeki 'host'
class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob(rtrim($dir, '/\\') . '/*.php') as $file) {
            $name = basename($file, '.php');
            if (str_contains($name, '.')) continue; // database.local.php gibi dosyalar asıl dosyanın içinden okunur
            self::$items[$name] = require $file;
        }
    }

    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value;
    }
}
