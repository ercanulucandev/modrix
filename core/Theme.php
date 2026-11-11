<?php
namespace Core;

use App\Models\User;
use RuntimeException;
use Throwable;

// Temalar: sitenin görünümü. Her tema app/Themes/{Ad}/ klasöründedir (bkz. docs/themes.md).
//
//   app/Themes/Redmo/
//   ├── theme.json            ad, sürüm, açıklama, menü konumları
//   ├── screenshot.png        Görünüm > Temalar'daki önizleme (svg/png/webp/jpg)
//   ├── settings.php          Özelleştir alanları (bölümler halinde)
//   ├── functions.php         temanın hook'ları (isteğe bağlı, sadece etkin temada yüklenir)
//   ├── views/                app/Views ile aynı yollar: layouts/site.php, home.php, errors/404.php ...
//   ├── modules/Blog/         modül görünümlerini ezer: Blog::index -> modules/Blog/index.php
//   └── assets/               css, js, görseller -> /theme/kurumsal/css/style.css  (Theme::asset())
//
// Görünüm arama sırası (sitede): etkin tema -> başlangıç teması (Baslangic) -> app/Views (ya da modülün Views'ı).
// Yani bir tema sadece değiştirmek istediği dosyaları içerir; olmayanlar Default'tan gelir.
// Yönetim paneli temadan etkilenmez.
//
//   Theme::active()                 etkin tema klasörü ('Baslangic')
//   Theme::get('hero_title')        etkin temanın Özelleştir değeri (yoksa tanımdaki varsayılan)
//   Theme::bool('sticky_header')    Theme::url('hero_image')   Theme::asset('css/style.css')
//   Theme::head($title, $desc)      <head> içine: başlık, SEO, favicon, analytics, özel kod, modül eklentileri
//   Theme::footer()                 </body> öncesine: özel kod, modül eklentileri (WhatsApp düğmesi vb.)
//   Theme::editable('hero', 'hero_title')   Özelleştir önizlemesinde tıklanabilir bölüm/alan işareti
//   Menu::location('header')        temanın menü konumuna atanmış menü (bkz. core/Menu.php)
class Theme
{
    public const DEFAULT = 'Baslangic';   // başlangıç teması (çekirdekle gelir, silinemez)
    public const SCREENSHOTS = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg'];
    private const PREVIEW_KEY = '_theme_preview';
    private const DRAFT_KEY = '_theme_customize';

    private static ?string $active = null;
    private static array $manifests = [];
    private static array $sections = [];

    // ================================================================ BULMA

    // ['Baslangic' => ['name', 'version', 'description', 'author', 'menus', 'folder', 'active', 'error'], ...]
    public static function all(): array
    {
        $themes = [];
        foreach (glob(ROOT . '/app/Themes/*', GLOB_ONLYDIR) as $dir) {
            $folder = basename($dir);
            if (!self::validName($folder)) continue;
            try {
                $t = self::manifest($folder);
                $t['error'] = null;
            } catch (Throwable $e) {
                $t = ['name' => $folder, 'version' => '?', 'description' => '', 'author' => '', 'menus' => [], 'error' => $e->getMessage()];
            }
            $t['folder'] = $folder;
            $t['active'] = $folder === self::saved();
            $themes[$folder] = $t;
        }
        // Etkin tema en başta, sonra ada göre
        uasort($themes, fn($a, $b) => [$b['active'], $a['name']] <=> [$a['active'], $b['name']]);
        return $themes;
    }

    public static function manifest(string $folder): array
    {
        if (isset(self::$manifests[$folder])) return self::$manifests[$folder];
        if (!self::validName($folder)) throw new RuntimeException("Geçersiz tema adı: $folder");

        $dir = ROOT . "/app/Themes/$folder";
        $m = is_file("$dir/theme.json") ? json_decode((string) file_get_contents("$dir/theme.json"), true) : null;
        if (!is_array($m)) throw new RuntimeException('theme.json bulunamadı ya da geçerli JSON değil');
        if (empty($m['name']) || !is_string($m['name'])) throw new RuntimeException('theme.json: "name" zorunlu');
        if (empty($m['version']) || !preg_match('/^\d+\.\d+\.\d+$/', (string) $m['version'])) throw new RuntimeException('theme.json: "version" 1.0.0 biçiminde olmalı');
        if (($php = $m['requires']['php'] ?? null) && version_compare(PHP_VERSION, $php, '<')) throw new RuntimeException("PHP $php ya da üstü gerekli");
        if ($folder !== self::DEFAULT && !is_file("$dir/views/layouts/site.php") && !is_dir(ROOT . '/app/Themes/' . self::DEFAULT)) {
            throw new RuntimeException('views/layouts/site.php bulunamadı');
        }

        // Menü konumları: { "header": "Üst menü", "footer": "Alt menü" }
        $menus = [];
        foreach ((array) ($m['menus'] ?? []) as $key => $label) {
            if (preg_match('/^[a-z][a-z0-9_]{0,30}$/', (string) $key) && is_string($label)) $menus[$key] = $label;
        }

        // Sayfa şablonları: { "landing": "Açılış sayfası" } -> app/Themes/{Tema}/modules/Pages/templates/landing.php
        $templates = [];
        foreach ((array) ($m['page_templates'] ?? []) as $key => $label) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,30}$/', (string) $key) && is_string($label)) $templates[$key] = $label;
        }

        // Varsayılan bölüm düzeni: { "home": [{ "type": "hero" }, { "type": "features", "data": {...} }] }
        $sections = [];
        foreach ((array) ($m['sections'] ?? []) as $area => $list) {
            if (preg_match('/^[a-z][a-z0-9_]{0,30}$/', (string) $area) && is_array($list)) $sections[$area] = array_values(array_filter($list, 'is_array'));
        }

        return self::$manifests[$folder] = [
            'sections'    => $sections,
            'templates'   => $templates,
            'name'        => $m['name'],
            'version'     => $m['version'],
            'description' => (string) ($m['description'] ?? ''),
            'author'      => (string) ($m['author'] ?? ''),
            'menus'       => $menus,
        ];
    }

    // Etkin olmayan temayı siler: klasörü, ayarları, bölüm düzeni ve menü atamaları. Etkin tema ve başlangıç teması silinemez.
    public static function delete(string $folder): void
    {
        if (!self::validName($folder) || !is_dir(ROOT . "/app/Themes/$folder")) throw new RuntimeException('Tema bulunamadı.');
        if ($folder === self::DEFAULT) throw new RuntimeException('Başlangıç teması silinemez: diğer temaların eksik dosyaları ondan gelir.');
        if ($folder === self::saved()) throw new RuntimeException('Etkin tema silinemez; önce başka bir temayı etkinleştirin.');
        if (self::previewing() === $folder) self::stopPreview();

        $slug = self::slug($folder);
        foreach (['theme_' . $slug . '\_%', 'menu_location_' . $slug . '\_%'] as $pattern) {
            Database::table('settings')->whereRaw('`key` LIKE ?', [$pattern])->delete();
        }
        Module::removeDir(ROOT . "/app/Themes/$folder");
        unset(self::$manifests[$folder]);
    }

    public static function exists(string $folder): bool
    {
        try {
            self::manifest($folder);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // Kayıtlı (yayındaki) tema
    public static function saved(): string
    {
        $theme = (string) Setting::get('theme', self::DEFAULT);
        return self::exists($theme) ? $theme : self::DEFAULT;
    }

    // Bu istekte kullanılan tema: admin önizleme yapıyorsa önizlenen, değilse kayıtlı tema
    public static function active(): string
    {
        return self::$active ??= self::customizing()['theme'] ?? self::previewing() ?? self::saved();
    }

    public static function slug(?string $folder = null): string
    {
        return strtolower($folder ?? self::active());
    }

    public static function activate(string $folder): void
    {
        self::manifest($folder); // bozuk tema etkinleştirilmesin
        Setting::set(['theme' => $folder]);
        self::$active = null;
        self::stopPreview();
        Hook::fire('theme.activated', $folder);
        Log::info("Tema etkinleştirildi: $folder");
    }

    // ---- Canlı özelleştirme (Görünüm > Özelleştir)
    //
    // Formdaki her değişiklik oturuma taslak olarak yazılır (kaydedilmez). Özelleştir ekranındaki iframe
    // siteyi bu taslakla gösterir; ziyaretçiler ve adminin diğer sekmeleri etkilenmez. "Yayınla" ile kaydedilir.
    // iframe şöyle tanınır: tarayıcının Sec-Fetch-Dest: iframe başlığı (ya da ilk açılışta ?customize_preview=1).

    public static function setDraft(string $folder, array $values): void
    {
        Session::set(self::DRAFT_KEY, ['theme' => $folder, 'values' => $values]);
        self::$active = null;
    }

    public static function draft(): ?array
    {
        $draft = Session::get(self::DRAFT_KEY);
        return is_array($draft) && self::exists((string) ($draft['theme'] ?? '')) ? $draft : null;
    }

    public static function clearDraft(): void
    {
        Session::forget(self::DRAFT_KEY);
    }

    // Bu istek Özelleştir önizlemesi mi? Öyleyse taslak (['theme', 'values']), değilse null
    public static function customizing(): ?array
    {
        $inFrame = ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe' || isset($_GET['customize_preview']);
        if (!$inFrame || str_starts_with(Request::path(), '/admin')) return null;
        $draft = self::draft();
        return $draft && User::admin() ? $draft : null;
    }

    // ---- Önizleme (sadece giriş yapmış admin, oturum boyunca)

    public static function startPreview(string $folder): void
    {
        self::manifest($folder);
        Session::set(self::PREVIEW_KEY, $folder);
    }

    public static function stopPreview(): void
    {
        Session::forget(self::PREVIEW_KEY);
    }

    public static function previewing(): ?string
    {
        $folder = Session::get(self::PREVIEW_KEY);
        if (!$folder || $folder === self::saved() || !self::exists($folder)) return null;
        return User::admin() ? $folder : null;
    }

    // ================================================================ GÖRÜNÜMLER

    // View::path çağırır: temada (ya da Default'ta) aynı yolda dosya varsa onun yolu, yoksa null.
    //   'home'        -> app/Themes/{Tema}/views/home.php
    //   'Blog::index' -> app/Themes/{Tema}/modules/Blog/index.php
    public static function view(string $name): ?string
    {
        if (!self::applies($name)) return null;

        if (str_contains($name, '::')) {
            [$module, $file] = explode('::', $name, 2);
            $relative = "modules/$module/$file.php";
        } else {
            $relative = "views/$name.php";
        }

        foreach (array_unique([self::active(), self::DEFAULT]) as $theme) {
            $path = ROOT . "/app/Themes/$theme/$relative";
            if (is_file($path)) return $path;
        }
        return null;
    }

    // Tema sadece sitede geçerli: yönetim paneli ve admin görünümleri asla temadan gelmez
    private static function applies(string $name): bool
    {
        if (str_starts_with(Request::path(), '/admin')) return false;
        $file = str_contains($name, '::') ? explode('::', $name, 2)[1] : $name;
        return !str_starts_with($file, 'admin/') && !str_starts_with($file, 'layouts/admin');
    }

    // Etkin temanın functions.php'si (App açılışında, modüllerden sonra)
    public static function boot(): void
    {
        if (str_starts_with(Request::path(), '/admin')) return;
        // Özelleştir önizlemesi: kaydedilmemiş değerler bu istekte ayar gibi okunur
        if ($draft = self::customizing()) Setting::preview($draft['values']);
        $file = ROOT . '/app/Themes/' . self::active() . '/functions.php';
        if (is_file($file)) require $file;
    }

    // ================================================================ ÖZELLEŞTİR

    // Temanın Özelleştir bölümleri (settings.php). Alan anahtarları kayıtta 'theme_{tema}_{alan}' olur:
    //   ['colors' => ['label' => 'Renkler', 'fields' => ['theme_default_primary_color' => [...]]], ...]
    public static function sections(?string $folder = null): array
    {
        $folder ??= self::active();
        if (isset(self::$sections[$folder])) return self::$sections[$folder];

        $file = ROOT . "/app/Themes/$folder/settings.php";
        $def = is_file($file) ? require $file : [];
        $prefix = self::settingPrefix($folder);

        $sections = [];
        foreach ((array) $def as $key => $section) {
            if (!preg_match('/^[a-z0-9_]{1,40}$/', (string) $key) || empty($section['fields'])) continue;
            $fields = [];
            foreach ((array) $section['fields'] as $name => $f) {
                if (!preg_match('/^[a-z0-9_]{1,60}$/', (string) $name) || empty($f['type']) || empty($f['label'])) continue;
                $fields[$prefix . $name] = $f;
            }
            if ($fields) $sections[$key] = [
                'label'       => (string) ($section['label'] ?? $key),
                'description' => (string) ($section['description'] ?? ''),
                'fields'      => $fields,
            ];
        }
        return self::$sections[$folder] = $sections;
    }

    public static function settingPrefix(?string $folder = null): string
    {
        return 'theme_' . self::slug($folder) . '_';
    }

    // Etkin temanın ayarı; kayıt yoksa settings.php'deki varsayılan, o da yoksa $default
    public static function get(string $key, $default = null)
    {
        $full = self::settingPrefix() . $key;
        $value = Setting::all()[$full] ?? null;
        if ($value !== null && $value !== '') return $value;

        foreach (self::sections() as $section) {
            if (isset($section['fields'][$full]['default'])) return $section['fields'][$full]['default'];
        }
        return $default;
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    // Görsel alanının adresi (medya kütüphanesinden seçilir)
    public static function url(string $key): ?string
    {
        $path = self::get($key);
        return $path ? Request::url('media/' . $path) : null;
    }

    // Renk ayarı (geçersizse varsayılan): CSS'e güvenle yazılır
    public static function color(string $key, string $fallback = '#18181b'): string
    {
        $value = (string) self::get($key, $fallback);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    }

    // Rengin üstünde okunacak yazı rengi (açık renge koyu, koyuya açık)
    public static function contrast(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#09090b' : '#fafafa';
    }

    // ================================================================ DOSYALAR

    // Etkin temanın dosyası: Theme::asset('css/style.css') -> /theme/kurumsal/css/style.css?v=...
    // Etkin temada yoksa Default'taki kullanılır.
    public static function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        foreach (array_unique([self::active(), self::DEFAULT]) as $theme) {
            $file = ROOT . "/app/Themes/$theme/assets/$path";
            if (is_file($file)) return Request::url('theme/' . self::slug($theme) . "/$path") . '?v=' . Module::assetVersion($file);
        }
        return Request::url('theme/' . self::slug() . "/$path");
    }

    // Adresteki küçük harfli addan tema klasörü: 'redmo' -> 'Redmo'
    public static function folderFromSlug(string $slug): ?string
    {
        if (!preg_match('/^[a-z0-9]{1,50}$/', $slug)) return null;
        foreach (glob(ROOT . '/app/Themes/*', GLOB_ONLYDIR) as $dir) {
            if (strtolower(basename($dir)) === $slug && self::validName(basename($dir))) return basename($dir);
        }
        return null;
    }

    public static function screenshotFile(string $folder): ?string
    {
        if (!self::validName($folder)) return null;
        foreach (array_keys(self::SCREENSHOTS) as $ext) {
            $file = ROOT . "/app/Themes/$folder/screenshot.$ext";
            if (is_file($file)) return $file;
        }
        return null;
    }

    public static function validName(string $folder): bool
    {
        return (bool) preg_match('/^[A-Z][A-Za-z0-9]{0,49}$/', $folder);
    }

    // ================================================================ LAYOUT YARDIMCILARI

    // Özelleştir önizlemesinde tıklanınca düzenlenecek bölümü/alanı işaretler (ziyaretçide boş döner, HTML temiz kalır):
    //   <section class="..." <?= Theme::editable('hero') ? >>                    bölüm: üzerine gelince "Düzenle" düğmesi
    //   <h1 <?= Theme::editable('hero', 'hero_title') ? >>...</h1>              alan: tıklayınca paneldeki alan açılır
    //   Theme::editable('identity', 'site_name')                                 "Site kimliği" alanları (site adı, logo...)
    // Bölüm adı temanın settings.php'sindeki anahtar ('hero', 'footer'...), alan adı da oradaki alan adıdır.
    public static function editable(string $section, ?string $field = null): string
    {
        if (!self::customizing()) return '';
        $section = htmlspecialchars($section, ENT_QUOTES);
        if ($field === null) return 'data-customize="' . $section . '"';
        $key = $section === 'identity' ? $field : self::settingPrefix() . $field;
        return 'data-customize-field="' . htmlspecialchars($key, ENT_QUOTES) . '" data-customize-section="' . $section . '"';
    }

    // <head> içine (WordPress'teki wp_head gibi). Tema layout'u: <?= Theme::head($title ?? null, $metaDescription ?? null, $metaImage ?? null) ? >
    // $image: sayfaya özel paylaşım görseli (adres); yoksa Ayarlar > Logo ve simge > Paylaşım görseli
    public static function head(?string $title = null, ?string $description = null, ?string $image = null): string
    {
        return (string) View::render('partials/theme-head', ['title' => $title, 'description' => $description, 'image' => $image]);
    }

    // </body> öncesine (wp_footer gibi)
    public static function footer(): string
    {
        $labels = null;
        if (self::customizing()) {
            $labels = ['identity' => 'Site kimliği'] + array_map(fn($section) => $section['label'], self::sections()) + Section::labels();
        }
        return (string) View::render('partials/theme-footer', ['preview' => self::previewing(), 'customize' => $labels]);
    }
}
