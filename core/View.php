<?php
namespace Core;

use RuntimeException;

// Görünümler.
//
// Sitede görünümler önce etkin temada aranır (app/Themes/{Tema}/views/..., modüller için app/Themes/{Tema}/modules/{Modül}/...),
// sonra başlangıç temasında (Baslangic), en son burada. Yönetim paneli temadan etkilenmez. Bkz. core/Theme.php
//
//   View::render('home')                    -> app/Themes/{Tema}/views/home.php  (yoksa app/Views/home.php)
//   View::render('errors/404')              -> app/Views/errors/404.php
//   View::render('Blog::index')             -> app/Modules/Blog/Views/index.php
//   View::render('home')->setLayout('app')  -> app/Views/layouts/app.php içine yerleşir ($content)
//
// Controller'da $this->view(...) kendi layout'unu otomatik ekler.
// View içinde başka view yazdırmak (partial) layout'a sarılmaz:  echo View::render('partials/alert', [...])
class View
{
    private string $name;
    private array $data;
    private ?string $layout = null;

    private function __construct(string $name, array $data)
    {
        $this->name = $name;
        $this->data = $data;
    }

    public static function render(string $name, array $data = []): self
    {
        return new self($name, $data);
    }

    public function setLayout(?string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function __toString(): string
    {
        $content = self::file(self::path($this->name), $this->data);
        if ($this->layout === null) return $content;

        return self::file(self::path('layouts/' . $this->layout), [...$this->data, 'content' => $content]);
    }

    // 'Blog::posts/index' -> app/Modules/Blog/Views/posts/index.php,  'home' -> app/Views/home.php
    public static function path(string $name): string
    {
        if ($theme = Theme::view($name)) return $theme;

        if (str_contains($name, '::')) {
            [$module, $file] = explode('::', $name, 2);
            return ROOT . "/app/Modules/$module/Views/$file.php";
        }
        return ROOT . "/app/Views/$name.php";
    }

    // ---- View içinde kullanılan yardımcılar

    // Güvenli yazdırma: View::e($post['title'])
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Core'un ortak dosyaları (app/Assets): View::asset('admin/media-page.js') -> /assets/app/admin/media-page.js?v=...
    // Modül dosyaları için: Module::asset('Blog', 'admin/posts-page.js')
    public static function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $file = ROOT . "/app/Assets/$path";
        return Request::url("assets/app/$path") . (is_file($file) ? '?v=' . Module::assetVersion($file) : '');
    }

    // Formların içine: View::csrf()
    public static function csrf(): string
    {
        return '<input type="hidden" name="_token" value="' . Session::token() . '">';
    }

    // Form hatası sonrası eski değer: View::old('title', $post['title'])
    public static function old(string $field, $default = null)
    {
        $old = Session::flash('old');
        return is_array($old) && array_key_exists($field, $old) ? $old[$field] : $default;
    }

    // Alanın doğrulama hatası: View::error('title')
    public static function error(string $field): ?string
    {
        return (Session::flash('errors') ?? [])[$field] ?? null;
    }

    // 1234.5 -> "1.234,50 ₺"
    public static function money($amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' ₺';
    }

    // '2026-11-10 10:15:00' -> "10.11.2026" (saat istenirse "10.11.2026 10:15")
    public static function date(?string $value, bool $time = false): string
    {
        return $value ? date($time ? 'd.m.Y H:i' : 'd.m.Y', strtotime($value)) : '—';
    }

    // Yolu bilinen bir dosyayı görünüm olarak yazdırır (ör. bölüm görünümleri: core/Section.php)
    public static function renderFile(string $path, array $data = []): string
    {
        return self::file($path, $data);
    }

    private static function file(string $__path, array $__data): string
    {
        if (!is_file($__path)) throw new RuntimeException('View bulunamadı: ' . str_replace(ROOT, '', $__path));

        extract($__data);
        ob_start();
        require $__path;
        return ob_get_clean();
    }
}
