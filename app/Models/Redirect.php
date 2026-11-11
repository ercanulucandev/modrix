<?php
namespace App\Models;

use Core\Database;
use Core\Menu;
use Core\Model;

// Yönlendirmeler (Admin > SEO). Sadece hiçbir sayfanın karşılamadığı adreslerde çalışır (Router::fallback).
//   'eski-sayfa'      -> 'yeni-sayfa'              tam eşleşme
//   'eski-klasor/*'   -> 'yeni-klasor/*'           altındaki her adres, kalan kısım korunarak
//   'kampanya'        -> 'https://baska-site.com'  dış adres
class Redirect extends Model
{
    protected static string $table = 'redirects';
    protected static array $fillable = ['source', 'target', 'code', 'is_auto'];

    public const CODES = [301 => '301 Kalıcı', 302 => '302 Geçici'];

    // Adres için hedef: ['url' => tam adres, 'code' => 301] ya da null
    public static function resolve(string $path): ?array
    {
        $path = self::normalize($path);
        if ($path === '') return null;

        $row = static::query()->where('source', $path)->first();
        $rest = '';
        if (!$row) {
            // Joker: en uzun eşleşen 'klasor/*'
            foreach (static::query()->whereRaw("source LIKE '%*'")->get() as $r) {
                $prefix = rtrim(substr($r['source'], 0, -1), '/');
                if (($path === $prefix || str_starts_with($path, $prefix . '/')) && (!$row || strlen($r['source']) > strlen($row['source']))) {
                    $row = $r;
                    $rest = ltrim(substr($path, strlen($prefix)), '/');
                }
            }
        }
        if (!$row) return null;

        $target = $row['target'];
        if (str_ends_with($target, '/*')) $target = substr($target, 0, -1) . $rest;
        elseif (str_ends_with($target, '*')) $target = substr($target, 0, -1) . $rest;

        static::query()->where('id', $row['id'])->update(['hits' => (int) $row['hits'] + 1, 'last_hit_at' => date('Y-m-d H:i:s')]);
        return ['url' => Menu::href($target === '' ? '/' : $target), 'code' => (int) $row['code']];
    }

    // Adres değişince (sayfa/yazı) otomatik: eski -> yeni. Zincir oluşmasın diye eskiye işaret edenler de yeniye çevrilir.
    public static function moved(string $from, string $to): void
    {
        $from = self::normalize($from);
        $to = self::normalize($to);
        if ($from === '' || $from === $to) return;

        Database::transaction(function () use ($from, $to) {
            static::query()->where('source', $to)->delete();                     // yeni adres artık gerçek bir sayfa
            static::query()->where('target', $from)->update(['target' => $to]);   // a -> b -> c yerine a -> c
            $now = date('Y-m-d H:i:s');
            Database::query(
                'INSERT INTO redirects (source, target, code, is_auto, created_at, updated_at) VALUES (?, ?, 301, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE target = VALUES(target), updated_at = VALUES(updated_at)',
                [$from, $to, $now, $now]
            );
        });
        Database::table('not_found_log')->where('path', $from)->delete();
    }

    // '/Eski-Sayfa/?x=1' -> 'Eski-Sayfa'
    public static function normalize(string $path): string
    {
        $path = (string) parse_url($path, PHP_URL_PATH);
        return trim(rawurldecode($path), '/');
    }

    public static function validTarget(string $target): bool
    {
        return $target === '/' || Menu::validUrl(rtrim($target, '*'));
    }
}
