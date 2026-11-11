<?php
namespace App\Controllers\Admin;

use App\Services\Activity;
use Core\Database;
use Core\Lang;
use Core\Setting;
use Core\Validator;
use Core\View;

// Admin > Sistem > Diller: dil ekle/kaldır/varsayılan, arayüz metinlerinin çevirisi
class LanguageController extends AdminController
{
    public function index(): View
    {
        return $this->view('admin/languages/index', ['title' => 'Diller', 'languages' => Lang::all(), 'counts' => $this->contentCounts()]);
    }

    public function store(): void
    {
        $code = strtolower(trim((string) Request::post('code', '')));
        $name = trim((string) Request::post('name', '')) ?: (Lang::KNOWN[$code] ?? '');
        if (!preg_match('/^[a-z]{2}$/', $code)) Validator::fail(['code' => 'Dil kodu iki harf olmalı (ör. en, de).']);
        if (Lang::exists($code)) Validator::fail(['code' => 'Bu dil zaten ekli.']);
        if ($name === '' || mb_strlen($name) > 40) Validator::fail(['name' => 'Dilin adını yazın.']);
        $this->save([...array_values(Lang::all()), ['code' => $code, 'name' => $name]]);
        Activity::log('settings.language', "Dil ekledi: $name ($code)", 'admin/languages');
        $this->redirect('admin/languages', "$name eklendi. Sayfaları ve yazıları çevirmek için listelerindeki dil sekmesini kullanın.");
    }

    public function makeDefault(string $code): void
    {
        if (!Lang::exists($code)) $this->abort(404);
        $all = Lang::all();
        $this->save([$all[$code], ...array_values(array_diff_key($all, [$code => true]))]);
        Activity::log('settings.language', 'Varsayılan dili değiştirdi: ' . Lang::name($code), 'admin/languages');
        $this->redirect('admin/languages', Lang::name($code) . ' varsayılan dil oldu; adreslerinde önek olmayacak.');
    }

    public function destroy(string $code): void
    {
        if (!Lang::exists($code)) $this->abort(404);
        if ($code === Lang::default()) $this->redirect('admin/languages', 'Varsayılan dil kaldırılamaz; önce başka bir dili varsayılan yapın.', 'error');
        $name = Lang::name($code);
        $this->save(array_values(array_diff_key(Lang::all(), [$code => true])));
        Activity::log('settings.language', "Dili kaldırdı: $name ($code)", 'admin/languages');
        $this->redirect('admin/languages', "$name kaldırıldı. O dildeki sayfa ve yazılar silinmedi; dili tekrar eklerseniz geri gelir.");
    }

    // Arayüz metinleri: koddaki Lang::t('...') metinleri + çevirileri
    public function strings(): View
    {
        $lang = (string) Request::get('lang', '');
        $targets = array_values(array_diff(array_keys(Lang::all()), [Lang::SOURCE]));
        if (!in_array($lang, $targets, true)) $lang = $targets[0] ?? '';
        $q = trim((string) Request::get('q', ''));
        $existing = $lang ? Database::table('translations')->where('lang', $lang)->pluck('translation', 'source') : [];
        $strings = array_values(array_filter(self::sources(), fn($s) => $q === '' || mb_stripos($s, $q) !== false || mb_stripos($existing[$s] ?? '', $q) !== false));
        return $this->view('admin/languages/strings', [
            'title' => 'Arayüz metinleri', 'lang' => $lang, 'targets' => $targets, 'strings' => $strings, 'existing' => $existing, 'q' => $q,
            'done' => count(array_filter(self::sources(), fn($s) => ($existing[$s] ?? '') !== '')), 'total' => count(self::sources()),
        ]);
    }

    public function saveStrings(): void
    {
        $lang = (string) Request::post('lang', '');
        if (!Lang::exists($lang) || $lang === Lang::SOURCE) $this->abort(422, 'Geçersiz dil.');
        $sources = array_flip(self::sources());
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ((array) Request::post('t', []) as $i => $translation) {
            $source = (string) (Request::post('s', [])[$i] ?? '');
            if (!isset($sources[$source])) continue;
            $translation = trim(mb_substr((string) $translation, 0, 1000));
            if ($translation === '') {
                Database::table('translations')->where('lang', $lang)->where('source', $source)->delete();
                continue;
            }
            Database::query('INSERT INTO translations (lang, source, translation, updated_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE translation = VALUES(translation), updated_at = VALUES(updated_at)', [$lang, $source, $translation, $now]);
            $count++;
        }
        Activity::log('settings.language', Lang::name($lang) . ' arayüz metinlerini kaydetti', "admin/languages/strings?lang=$lang");
        $this->redirect('admin/languages/strings?lang=' . $lang . (($q = Request::post('q')) ? '&q=' . urlencode((string) $q) : ''), 'Çeviriler kaydedildi.');
    }

    // Koddaki çevrilebilir metinler (app/ altındaki PHP dosyalarında Lang::t('...'))
    public static function sources(): array
    {
        static $found = null;
        if ($found !== null) return $found;
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') continue;
            if (preg_match_all("/Lang::t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file->getPathname()), $m)) {
                foreach ($m[1] as $s) $found[stripslashes($s)] = true;
            }
        }
        ksort($found, SORT_LOCALE_STRING);
        return $found = array_keys($found);
    }

    private function save(array $languages): void
    {
        Setting::set(['languages' => json_encode(array_values(array_map(fn($l) => ['code' => $l['code'], 'name' => $l['name']], $languages)), JSON_UNESCAPED_UNICODE)]);
        Lang::forget();
    }

    private function contentCounts(): array
    {
        $counts = [];
        foreach (['pages' => 'Sayfa', 'blog_posts' => 'Yazı'] as $table => $label) {
            try {
                foreach (Database::all("SELECT lang, COUNT(*) n FROM `$table` GROUP BY lang") as $r) $counts[$r['lang']][] = "{$r['n']} $label";
            } catch (\Throwable) {
            }
        }
        return $counts;
    }
}
