<?php
namespace App\Models;

use Core\Model;
use Core\Session;

class User extends Model
{

    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password', 'role', 'status',
                                        'two_factor_secret', 'two_factor_recovery', 'two_factor_last_step', 'two_factor_confirmed_at'];

    private static array|false|null $admin = null;

    public static function findByEmail(string $email): ?array
    {
        return static::first(['email' => mb_strtolower(trim($email))]);
    }

    // Şifreyi hash'leyerek oluşturur
    public static function register(array $data): int
    {
        return static::create([
            ...$data,
            'email'    => mb_strtolower(trim($data['email'])),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
    }

    // ---- Admin oturumu (site kullanıcılarının 'user_id' oturumundan ayrı)

    // E-posta/şifre doğru, rolü panel rolü (roles tablosu) ve hesabı aktifse kullanıcı (giriş yapmaz; 2FA için)
    public static function checkAdmin(string $email, string $password): ?array
    {
        $user = static::findByEmail($email);
        if (!$user || !\App\Services\Access::isPanelRole($user['role']) || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
            return null;
        }
        return $user;
    }

    public static function loginAdmin(array $user): void
    {
        Session::regenerate();
        Session::set('admin_id', (int) $user['id']);
        self::$admin = null;
    }

    // Şifreyle doğrudan giriş (iki adımlı doğrulama kullanmayan yerler için)
    public static function attemptAdmin(string $email, string $password): bool
    {
        $user = static::checkAdmin($email, $password);
        if (!$user || \App\Services\TwoFactor::enabled($user)) return false;
        static::loginAdmin($user);
        return true;
    }

    // Giriş yapmış admin (her istekte veritabanından taze okunur; pasife alınırsa null)
    public static function admin(): ?array
    {
        if (self::$admin === null) {
            $id   = Session::get('admin_id');
            $user = $id ? static::find((int) $id) : null;
            self::$admin = $user && \App\Services\Access::isPanelRole($user['role']) && $user['status'] === 'active' ? $user : false;
        }
        return self::$admin ?: null;
    }

    public static function logoutAdmin(): void
    {
        Session::forget('admin_id');
        Session::regenerate();
        self::$admin = null;
    }
}
