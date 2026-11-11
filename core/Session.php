<?php
namespace Core;

class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'use_strict_mode' => true,
        ]);

        // Flash: önceki istekte yazılanlar bu istek boyunca okunabilir, sonra silinir
        $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
        unset($_SESSION['_flash_next']);
    }

    public static function get(string $key, $default = null) { return $_SESSION[$key] ?? $default; }
    public static function set(string $key, $value): void    { $_SESSION[$key] = $value; }
    public static function has(string $key): bool            { return isset($_SESSION[$key]); }
    public static function forget(string $key): void         { unset($_SESSION[$key]); }

    // Girişte çağrılır (session fixation koruması)
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    // Bir sonraki sayfada okunacak veri:
    //   Session::flash('ok', 'Kaydedildi')   ->   sonraki istekte Session::flash('ok')
    public static function flash(string $key, $value = null)
    {
        if (func_num_args() === 2) {
            $_SESSION['_flash_next'][$key] = $value;
            return null;
        }
        return $_SESSION['_flash'][$key] ?? null;
    }

    // CSRF anahtarı: formlarda View::csrf(), kontrolü CsrfMiddleware yapar
    public static function token(): string
    {
        return $_SESSION['_token'] ??= bin2hex(random_bytes(32));
    }
}
