<?php
namespace App\Services;

use Core\Database;
use Core\Mail;
use Core\Request;
use Core\Setting;
use Core\View;

// Şifre belirleme / sıfırlama bağlantıları (tek kullanımlık, süreli). Hem "Şifremi unuttum" hem yeni kullanıcı daveti kullanır.
final class PasswordReset
{
    public const TTL_MINUTES = 60;
    public const INVITE_TTL_MINUTES = 60 * 24 * 3;   // davet: 3 gün

    // Bağlantıyı e-postayla gönderir. $invite: yeni hesap ("şifrenizi belirleyin") metni
    public static function send(array $user, bool $invite = false): bool
    {
        $email = mb_strtolower($user['email']);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + ($invite ? self::INVITE_TTL_MINUTES : self::TTL_MINUTES) * 60);
        Database::query(
            'REPLACE INTO password_resets (email, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?)',
            [$email, hash('sha256', $token), date('Y-m-d H:i:s'), $expires]
        );

        $link = self::absolute('admin/reset/' . $token . '?email=' . rawurlencode($email));
        $site = (string) Setting::get('site_name', 'Site');
        $hours = $invite ? self::INVITE_TTL_MINUTES / 60 : null;

        $subject = $invite ? "Hesabınız oluşturuldu · $site" : "Şifre sıfırlama · $site";
        $intro = $invite
            ? "$site yönetim paneline sizin için bir hesap açıldı. Şifrenizi belirleyip giriş yapmak için:"
            : "$site yönetim paneli için şifre sıfırlama isteği aldık. Yeni şifre belirlemek için:";
        $validity = $invite ? "Bağlantı $hours saat geçerlidir." : 'Bağlantı ' . self::TTL_MINUTES . ' dakika geçerlidir. Bu isteği siz yapmadıysanız bu e-postayı dikkate almayın.';

        return Mail::send($email, $subject, "Merhaba {$user['name']},\n\n$intro\n\n$link\n\n$validity\n", ['html' =>
            '<p>Merhaba ' . View::e($user['name']) . ',</p><p>' . View::e($intro) . '</p>'
            . '<p><a href="' . View::e($link) . '" style="display:inline-block;padding:10px 18px;background:#18181b;color:#fff;border-radius:6px;text-decoration:none">'
            . ($invite ? 'Şifremi belirle' : 'Yeni şifre belirle') . '</a></p>'
            . '<p style="color:#71717a;font-size:13px">' . View::e($validity) . '</p>']);
    }

    public static function recentlySent(string $email): bool
    {
        return Database::table('password_resets')->where('email', mb_strtolower($email))
            ->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))->exists();
    }

    public static function valid(string $email, string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || $email === '') return false;
        $row = Database::table('password_resets')->where('email', mb_strtolower($email))->first();
        return $row
            && hash_equals($row['token_hash'], hash('sha256', $token))
            && strtotime($row['expires_at'] ?? $row['created_at'] . ' +' . self::TTL_MINUTES . ' minutes') > time();
    }

    public static function forget(string $email): void
    {
        Database::table('password_resets')->where('email', mb_strtolower($email))->delete();
    }

    private static function absolute(string $path): string
    {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . Request::url($path);
    }
}
