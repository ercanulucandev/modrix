<?php
namespace App\Controllers;

use App\Models\User;
use Core\Controller;
use Core\Module;

// Modül dosyaları: /assets/{modül}/{yol}  ->  app/Modules/{Modül}/Assets/{yol}
//   /assets/blog/admin/posts-page.js     ->  app/Modules/Blog/Assets/admin/posts-page.js
//   /assets/app/lib/modal.js             ->  app/Assets/lib/modal.js   (core'un ortak arayüz kiti; "app" modül adı olarak kullanılamaz)
// Adresteki modül adı küçük harfle yazılır. Sadece AKTİF modüller, izinli türler; gizli dosyalar ve ../ reddedilir.
// .../admin/... altındaki dosyalar sadece giriş yapmış admin'e sunulur.
// Önbellek: her istekte ETag ile doğrulanır (değişmediyse 304). View içinde adres için Module::asset().
class ModuleAssetController extends Controller
{
    public const TYPES = [
        'js'    => 'text/javascript; charset=utf-8',
        'mjs'   => 'text/javascript; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];

    public function show(string $module, string $path): void
    {
        $path  = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $type  = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        // "app" -> core'un ortak dosyaları (app/Assets); diğerleri: "blog" -> aktif modül klasörü "Blog"
        $root = $module === 'app'
            ? ROOT . '/app/Assets'
            : (($folder = Module::activeFolder($module)) ? ROOT . "/app/Modules/$folder/Assets" : null);
        if (!$type || !$root) $this->abort(404);
        foreach ($parts as $p) {
            if ($p === '' || $p === '..' || $p[0] === '.') $this->abort(404);
        }

        // Yolunda "admin" klasörü geçen dosyalar (ör. admin/posts-page.js) sadece giriş yapmış admin'e.
        // Diğerlerine 404 (dosyanın varlığı da belli olmasın). Yönetim paneli dışında kullanılan JS/CSS bu klasöre konmaz.
        if (in_array('admin', array_map('strtolower', array_slice($parts, 0, -1)), true) && !User::admin()) $this->abort(404);

        $file = "$root/$path";
        if (!is_file($file)) $this->abort(404);

        self::send($file, $type, User::admin() !== null);
    }

    // Dosyayı ETag ile gönderir (değişmediyse 304). Tema dosyaları da kullanır.
    public static function send(string $file, string $type, bool $private = false): never
    {
        $etag = '"' . Module::assetVersion($file) . '"';
        header('ETag: ' . $etag);
        // admin dosyaları paylaşılan önbelleklerde (proxy/CDN) tutulmasın
        header('Cache-Control: ' . ($private ? 'private' : 'public') . ', max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        if ($type === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");

        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
