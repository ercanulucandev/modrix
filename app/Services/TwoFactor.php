<?php
namespace App\Services;

use App\Models\User;
use Core\Setting;

// İki adımlı doğrulama: telefondaki doğrulayıcı uygulamanın (Google Authenticator, Microsoft Authenticator,
// 1Password...) 30 saniyede bir değişen 6 haneli kodu (TOTP, RFC 6238) ya da tek kullanımlık kurtarma kodları.
final class TwoFactor
{
    public const RECOVERY_COUNT = 8;
    private const PERIOD = 30;
    private const WINDOW = 1;   // telefon saati ±30 sn kaymış olabilir

    public static function enabled(?array $user): bool
    {
        return !empty($user['two_factor_confirmed_at']) && !empty($user['two_factor_secret']);
    }

    // Ayarlar > Güvenlik: tüm panel kullanıcıları için zorunlu mu
    public static function required(): bool
    {
        return Setting::bool('two_factor_required');
    }

    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    // Uygulamaya eklemek için QR koduna konan adres
    public static function uri(string $secret, string $email): string
    {
        $issuer = (string) Setting::get('site_name', 'Modrix');
        return 'otpauth://totp/' . rawurlencode("$issuer:$email") . '?' . http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => 6, 'period' => self::PERIOD]);
    }

    // Kod doğruysa eşleşen zaman adımı (daha önce kullanılmış adım kabul edilmez), değilse null
    public static function check(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return null;
        $now = intdiv(time(), self::PERIOD);
        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if ($lastStep !== null && $step <= $lastStep) continue;
            if (hash_equals(self::code($secret, $step), $code)) return $step;
        }
        return null;
    }

    // Girişte: uygulama kodu ya da kurtarma kodu. Kullanılan kod bir daha geçmez.
    public static function verify(array $user, string $input): bool
    {
        $secret = Crypt::decrypt($user['two_factor_secret']);
        if ($secret === null) return false;

        $step = self::check($secret, $input, $user['two_factor_last_step'] !== null ? (int) $user['two_factor_last_step'] : null);
        if ($step !== null) {
            User::update($user['id'], ['two_factor_last_step' => $step]);
            return true;
        }

        $hash = self::hashRecovery($input);
        $codes = json_decode((string) $user['two_factor_recovery'], true) ?: [];
        foreach ($codes as $i => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$i]);
                User::update($user['id'], ['two_factor_recovery' => json_encode(array_values($codes))]);
                Activity::log('auth.2fa', 'Kurtarma koduyla giriş yaptı (' . count($codes) . ' kod kaldı)', 'admin/profile/security', $user);
                return true;
            }
        }
        return false;
    }

    // Kurulum doğrulandıktan sonra açar; kurtarma kodlarını döndürür (kullanıcıya bir kez gösterilir)
    public static function enable(int $userId, string $secret, int $step): array
    {
        $codes = self::recoveryCodes();
        User::update($userId, [
            'two_factor_secret'       => Crypt::encrypt($secret),
            'two_factor_recovery'     => json_encode(array_map(self::hashRecovery(...), $codes)),
            'two_factor_last_step'    => $step,
            'two_factor_confirmed_at' => date('Y-m-d H:i:s'),
        ]);
        return $codes;
    }

    public static function regenerateRecovery(int $userId): array
    {
        $codes = self::recoveryCodes();
        User::update($userId, ['two_factor_recovery' => json_encode(array_map(self::hashRecovery(...), $codes))]);
        return $codes;
    }

    public static function remainingRecovery(array $user): int
    {
        return count(json_decode((string) $user['two_factor_recovery'], true) ?: []);
    }

    public static function disable(int $userId): void
    {
        User::update($userId, ['two_factor_secret' => null, 'two_factor_recovery' => null, 'two_factor_last_step' => null, 'two_factor_confirmed_at' => null]);
    }

    // ---- İç

    private static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1_000_000;
        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private static function recoveryCodes(): array
    {
        return array_map(fn() => implode('-', str_split(bin2hex(random_bytes(5)), 5)), range(1, self::RECOVERY_COUNT));
    }

    private static function hashRecovery(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[^a-f0-9]/i', '', $code)));
    }

    private static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    private static function base32Decode(string $text): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text))) as $c) $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }
}
