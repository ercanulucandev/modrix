<?php
namespace App\Services;

use App\Models\User;
use Core\Config;
use Core\Database;
use Core\Migrator;
use Core\Module;
use Core\Request;
use Core\Session;
use Core\Setting;
use Core\View;
use PDO;
use Throwable;

// Kurulum sihirbazı (/install): yeni bir sunucuda tarayıcıdan kurulum.
//   1. Gereksinimler (PHP, eklentiler, yazılabilir klasörler)
//   2. Veritabanı (bağlantı denenir, yoksa oluşturulur) -> app/Config/database.local.php
//   3. Site ve yönetici (tablolar, ayarlar, admin hesabı) + mağazadan seçilen modüller ve tema -> storage/installed.lock
// Kurulu sitede (kilit dosyası ya da veritabanında kullanıcı tablosu varsa) devreye girmez; /install 404 olur.
final class Installer
{
    // Mağazadan önerilen (kurulumda işaretli gelen) modüller
    public const RECOMMENDED = ['Pages', 'Blog', 'Forms', 'Cookies'];
    private const LOCK = '/storage/installed.lock';

    public static function installed(): bool
    {
        if (is_file(ROOT . self::LOCK)) return true;
        // Eski kurulumlar: kilit dosyası yok ama veritabanında kullanıcı var.
        // Veritabanı ayarı hiç yazılmadıysa bakılmaz: yeni kopya, varsayılan adla duran başka bir sitenin veritabanına bağlanmasın.
        if (!is_file(ROOT . '/app/Config/database.local.php')) return false;
        try {
            if (Database::pdo()->query("SHOW TABLES LIKE 'users'")->fetchColumn() && Database::value('SELECT COUNT(*) FROM users') > 0) {
                @file_put_contents(ROOT . self::LOCK, date('c') . "\n");
                return true;
            }
        } catch (Throwable) {
        }
        return false;
    }

    // Kurulmamış sitede her isteği karşılar
    public static function handle(): void
    {
        $path = Request::path();
        if (!str_starts_with($path, '/install')) {
            if (str_starts_with($path, '/media') || str_starts_with($path, '/assets')) { http_response_code(404); return; }
            header('Location: ' . Request::url('install'));
            return;
        }
        $step = Session::get('install_step', 1);
        $error = null;
        if (Request::method() === 'POST') {
            if (!hash_equals(Session::token(), (string) Request::post('_token'))) $error = 'Oturum süresi doldu; sayfayı yenileyip tekrar deneyin.';
            else {
                try {
                    $step = match ((int) Request::post('step')) {
                        1 => self::requirementsOk() ? 2 : 1,
                        2 => self::saveDatabase(),
                        3 => self::finish(),
                        default => $step,
                    };
                    Session::set('install_step', $step);
                    if ($step === 4) { header('Location: ' . Request::url('admin')); return; }
                    header('Location: ' . Request::url('install'));
                    return;
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }
        if ((int) Request::get('step') === 1) { $step = 1; Session::set('install_step', 1); }
        echo View::render("install/step$step", ['step' => $step, 'error' => $error, 'requirements' => self::requirements(), 'old' => $_POST])->setLayout('install');
    }

    // [ad, durum (bool), açıklama]
    public static function requirements(): array
    {
        $writable = fn($dir) => is_dir(ROOT . "/$dir") && is_writable(ROOT . "/$dir");
        $out = [
            ['PHP 8.1 ya da yenisi', version_compare(PHP_VERSION, '8.1.0', '>='), 'Sunucuda PHP ' . PHP_VERSION],
            ['PDO MySQL', extension_loaded('pdo_mysql'), 'Veritabanı bağlantısı'],
            ['mbstring', extension_loaded('mbstring'), 'Türkçe karakterler'],
            ['fileinfo', extension_loaded('fileinfo'), 'Dosya türü denetimi'],
            ['sodium', extension_loaded('sodium'), 'Şifreleme (iki adımlı doğrulama)'],
            ['GD', extension_loaded('gd'), 'Görsel küçültme, WebP (önerilir)', true],
            ['zip', class_exists('ZipArchive'), 'Yedek, modül ve güncelleme paketleri (önerilir)', true],
            ['cURL', extension_loaded('curl'), 'Güncellemeler, ödeme (önerilir)', true],
        ];
        foreach (['storage', 'storage/uploads', 'app/Config', 'app/Modules', 'app/Themes'] as $dir) $out[] = ["$dir yazılabilir", $writable($dir), 'Klasör izinleri (755/775)'];
        return $out;
    }

    private static function requirementsOk(): bool
    {
        foreach (self::requirements() as $r) if (!$r[1] && empty($r[3])) throw new \RuntimeException('Zorunlu gereksinimler karşılanmıyor: ' . $r[0]);
        return true;
    }

    private static function saveDatabase(): int
    {
        $c = [
            'host' => trim((string) Request::post('host', '127.0.0.1')) ?: '127.0.0.1',
            'port' => (int) Request::post('port', 3306) ?: 3306,
            'name' => trim((string) Request::post('name', '')),
            'user' => trim((string) Request::post('user', '')),
            'pass' => (string) Request::post('pass', ''),
        ];
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $c['name'])) throw new \RuntimeException('Veritabanı adı sadece harf, rakam ve _ içerebilir.');
        if ($c['user'] === '') throw new \RuntimeException('Veritabanı kullanıcı adını yazın.');
        try {
            $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$c['name']}`");
            if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() && !Request::post('reuse')) {
                throw new \RuntimeException('Bu veritabanında zaten bir Modrix kurulumu var. Başka bir veritabanı seçin ya da "mevcut veritabanını kullan"ı işaretleyin.');
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException('Veritabanına bağlanılamadı: ' . $e->getMessage());
        }
        $php = "<?php\n// Veritabanı bağlantısı (kurulum sihirbazı yazdı; git'e girmez)\nreturn " . var_export($c, true) . ";\n";
        if (file_put_contents(ROOT . '/app/Config/database.local.php', $php) === false) throw new \RuntimeException('app/Config/database.local.php yazılamadı (klasör izinleri).');
        return 3;
    }

    private static function finish(): int
    {
        $site = trim((string) Request::post('site_name', ''));
        $name = trim((string) Request::post('admin_name', ''));
        $email = mb_strtolower(trim((string) Request::post('admin_email', '')));
        $pass = (string) Request::post('admin_password', '');
        if ($site === '' || mb_strlen($site) > 100) throw new \RuntimeException('Site adını yazın.');
        if ($name === '' || mb_strlen($name) > 100) throw new \RuntimeException('Adınızı yazın.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Geçerli bir e-posta yazın.');
        if (mb_strlen($pass) < 8 || $pass !== Request::post('admin_password_confirmation')) throw new \RuntimeException('Şifre en az 8 karakter olmalı ve iki kez aynı yazılmalı.');

        Config::load(ROOT . '/app/Config');   // yeni database.local.php
        Migrator::migrate();
        Setting::set(['site_name' => $site, 'contact_email' => $email, 'mail_from_name' => $site]);
        $existing = User::findByEmail($email);
        $id = $existing ? (int) $existing['id'] : User::register(['name' => $name, 'email' => $email, 'password' => $pass, 'role' => 'admin']);

        // Mağazadan seçilen modüller ve tema (indirilir, SHA-256 doğrulanır, kurulur)
        $failed = [];
        foreach ((array) Request::post('modules', []) as $slug) {
            if (!preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', (string) $slug)) continue;
            try {
                if (is_dir(ROOT . "/app/Modules/$slug")) {
                    if ((Module::all()[$slug]['state'] ?? '') === 'not_installed') Module::install($slug);
                } else {
                    Store::install($slug, 'modules');
                }
            } catch (Throwable $e) {
                $failed[] = $slug;
            }
        }
        $theme = (string) Request::post('theme', \Core\Theme::DEFAULT);
        if ($theme !== \Core\Theme::DEFAULT && preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', $theme)) {
            try {
                if (!is_dir(ROOT . "/app/Themes/$theme")) Store::install($theme, 'themes');
                \Core\Theme::activate($theme);
            } catch (Throwable $e) {
                $failed[] = "$theme (tema)";
            }
        }
        file_put_contents(ROOT . self::LOCK, date('c') . "\n");
        Session::forget('install_step');
        User::loginAdmin(User::find($id));
        Session::flash('ok', "Kurulum tamamlandı, hoş geldiniz! Ayarlar > E-posta'dan e-posta gönderimini ayarlamayı unutmayın." . ($failed ? ' Kurulamayanlar: ' . implode(', ', $failed) . ' (Sistem > Mağaza\'dan tekrar deneyin).' : ''));
        return 4;
    }
}
