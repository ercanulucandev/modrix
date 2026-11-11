<?php
namespace App\Services;

use RuntimeException;

// Veritabanında saklanan gizli değerleri (2FA anahtarı vb.) şifreler. Anahtar app/Config/key.local.php'de
// (git'e girmez, ilk kullanımda kendiliğinden oluşur). Bu dosya kaybolursa şifreli değerler okunamaz:
// sunucu taşırken ve yedek alırken bu dosyayı da kopyalayın.
//
//   $sealed = Crypt::encrypt('gizli');   Crypt::decrypt($sealed)  // hatalıysa null
final class Crypt
{
    private static ?string $key = null;

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $sealed): ?string
    {
        $raw = base64_decode((string) $sealed, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $plain === false ? null : $plain;
    }

    // Sunucuya özel imza (oturumdan bağımsız; önbellekteki sayfalarda da geçerli). Ör. formlardaki zaman damgası
    public static function sign(string $value, int $length = 16): string
    {
        return substr(hash_hmac('sha256', $value, self::key()), 0, $length);
    }

    private static function key(): string
    {
        if (self::$key !== null) return self::$key;
        $file = ROOT . '/app/Config/key.local.php';
        if (!is_file($file)) {
            $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            $php = "<?php\n// Şifreleme anahtarı (App\\Services\\Crypt). Silmeyin, değiştirmeyin; sunucu taşırken kopyalayın. Git'e girmez.\nreturn '$key';\n";
            if (file_put_contents($file, $php, LOCK_EX) === false) throw new RuntimeException('app/Config/key.local.php yazılamadı.');
        }
        $key = base64_decode((string) require $file, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('app/Config/key.local.php geçersiz.');
        return self::$key = $key;
    }
}
