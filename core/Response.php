<?php
namespace Core;

use finfo;

class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . (preg_match('#^https?://#', $path) ? $path : Request::url($path)));
        exit;
    }

    public static function back(): never
    {
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? Request::url('/')));
        exit;
    }

    public static function json(array $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // İşlemi keser ve hata sayfası gösterir: Response::abort(403), Response::abort(404, 'Yazı bulunamadı')
    public static function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    public static function status(int $code): void
    {
        http_response_code($code);
    }

    // Dosya gönderir. Resim ve PDF tarayıcıda açılır, diğerleri indirilir.
    public static function download(string $file, ?string $name = null): never
    {
        if (!is_file($file)) self::abort(404, 'Dosya bulunamadı');

        $mime   = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        $inline = (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') || $mime === 'application/pdf';
        $name   = $name ?? basename($file);
        $ascii  = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
