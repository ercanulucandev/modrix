<?php
namespace App\Controllers;

use Core\Controller;
use Core\Response;
use Core\Upload;

// Herkese açık yüklenen dosyalar: /media/media/2026/11/abc.webp (kütüphane), /media/settings/... (eski ayar görselleri)
// Sadece izin verilen klasörler ve türler sunulur. Görseller ve PDF tarayıcıda açılır, diğerleri indirilir.
class MediaController extends Controller
{
    private const PUBLIC_DIRS = ['media', 'settings'];
    private const TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];

    public function show(string $path): void
    {
        $path  = str_replace('\\', '/', $path);
        $parts = explode('/', $path);

        if (!in_array($parts[0], self::PUBLIC_DIRS, true)
            || array_filter($parts, fn($p) => $p === '' || $p === '..' || $p[0] === '.')
            || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true)) {
            $this->abort(404);
        }

        $file = Upload::path($path);
        if (!is_file($file)) $this->abort(404);

        // Görsel düzenleyici "yerine kaydet" ile içerik aynı adreste değişebilir:
        // 5 dk önbellek, sonra ETag ile doğrulama (değişmediyse 304, gövde gönderilmez)
        $etag = '"' . substr(md5(filemtime($file) . '-' . filesize($file)), 0, 16) . '"';
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
        header('Cache-Control: public, max-age=300, must-revalidate');
        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }

        Response::download($file, basename($path));
    }
}
