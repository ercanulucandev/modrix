<?php
namespace App\Controllers\Admin;

use App\Controllers\ModuleAssetController;
use App\Services\SettingForm;
use Core\Icon;
use Core\Menu;
use Core\Section;
use Core\Setting;
use Core\Theme;
use Core\View;
use Throwable;

// Admin > Görünüm: temalar (listele, önizle, etkinleştir), Özelleştir (temanın ayarları + canlı önizleme), Menüler.
// Menülerin kaydı API ile: MenuApiController.
class AppearanceController extends AdminController
{
    // ---- Temalar

    public function index(): View
    {
        return $this->view('admin/appearance/themes', [
            'title'   => 'Görünüm',
            'themes'  => Theme::all(),
            'preview' => Theme::previewing(),
        ]);
    }

    public function activate(string $name): void
    {
        $theme = $this->theme($name);
        Theme::activate($name);
        $this->redirect('admin/appearance', "{$theme['name']} teması etkinleştirildi.");
    }

    public function destroy(string $name): void
    {
        $theme = Theme::all()[$name] ?? $this->abort(404);
        try {
            Theme::delete($name);
        } catch (Throwable $e) {
            $this->redirect('admin/appearance', $e->getMessage(), 'error');
        }
        \App\Services\Activity::log('appearance.theme', "Temayı sildi: {$theme['name']}", 'admin/appearance');
        $this->redirect('admin/appearance', "{$theme['name']} teması silindi.");
    }

    // Siteyi bu temayla gez (sadece bu admin görür, ziyaretçiler etkin temayı görmeye devam eder)
    public function preview(string $name): void
    {
        $this->theme($name);
        Theme::startPreview($name);
        $this->redirect('/');
    }

    public function stopPreview(): void
    {
        Theme::stopPreview();
        $this->redirect('admin/appearance', 'Önizleme kapatıldı.');
    }

    public function screenshot(string $name): never
    {
        $file = Theme::screenshotFile($name) ?? $this->abort(404);
        ModuleAssetController::send($file, Theme::SCREENSHOTS[pathinfo($file, PATHINFO_EXTENSION)], true);
    }

    // ---- Özelleştir: solda bölümler + form, sağda sitenin canlı önizlemesi (iframe)

    // Tam ekran canlı düzenleyici (WordPress Customizer gibi): solda açılır-kapanır panel, sağda site.
    // Her değişiklik taslak olarak gönderilir (draft) ve önizleme yenilenir; "Yayınla" hepsini kaydeder (publish).
    public function customize(): View
    {
        $folder = (string) Request::get('theme', Theme::saved());
        $theme = $this->theme($folder);

        // Sayfa yenilenince aynı temanın kaydedilmemiş değişiklikleri korunur; başka temaya geçilince sıfırlanır
        $draft = Theme::draft();
        $draftValues = $draft && $draft['theme'] === $folder ? $draft['values'] : [];
        Theme::setDraft($folder, $draftValues);

        // Bölüm alanları (ana sayfa vb.): düzen + kullanılabilir bölüm türleri (JS düzenleyicisi için)
        $values = array_merge(Setting::all(), $draftValues);
        $areas = [];
        foreach (Section::areas($folder) as $area) {
            $key = Section::key($area, $folder);
            $list = isset($values[$key]) ? json_decode((string) $values[$key], true) : null;
            $areas[$area] = [
                'key'       => $key,
                'label'     => self::AREA_LABELS[$area] ?? ucfirst($area) . ' bölümleri',
                'instances' => is_array($list) ? Section::normalize($list, $folder) : Section::instances($area, $folder),
            ];
        }
        $types = array_map(fn($t) => [
            'id' => $t['id'], 'name' => $t['name'], 'description' => $t['description'], 'icon' => $t['icon'], 'source' => $t['source'],
            'fields' => $t['fields'], 'defaults' => Section::clean($t['fields'], []),
        ], Section::types($folder));

        $this->layout = 'admin-customizer';
        return $this->view('admin/appearance/customize', [
            'sectionData' => ['areas' => $areas, 'types' => $types, 'icons' => Icon::all(), 'mediaBase' => Request::url('media/')],
            'title'    => 'Özelleştir: ' . $theme['name'],
            'folder'   => $folder,
            'theme'    => $theme,
            'themes'   => array_filter(Theme::all(), fn($t) => !$t['error']),
            'isActive' => $folder === Theme::saved(),
            'sections' => self::customizerSections($folder),
            'values'   => $values,
            'dirty'    => (bool) $draftValues,
        ]);
    }

    // Taslak: { values: { alan: değer } } -> oturuma (sadece bu temanın Özelleştir alanları)
    public function draft(string $name): string
    {
        $this->theme($name);
        $allowed = self::customizerFields($name);
        $values = [];
        foreach ((array) Request::post('values', []) as $key => $value) {
            if (!isset($allowed[$key]) || !is_scalar($value) && $value !== null) continue;
            $values[$key] = mb_substr((string) $value, 0, $allowed[$key]['type'] === 'sections' ? 500000 : 10000);
        }
        Theme::setDraft($name, $values);
        return $this->json(['ok' => true]);
    }

    // Yayınla: tüm alanlar doğrulanıp kaydedilir; _activate gelirse tema da etkinleştirilir
    public function publish(string $name): string
    {
        $theme = $this->theme($name);
        $fields = self::customizerFields($name);
        SettingForm::save(array_filter($fields, fn($f) => $f['type'] !== 'sections'), false);   // hata varsa 422 + alan hataları

        // Bölüm düzenleri: JSON doğrulanıp temizlenerek kaydedilir (bilinmeyen tür/alan atılır)
        foreach ($fields as $key => $f) {
            if ($f['type'] !== 'sections') continue;
            $list = json_decode((string) Request::post($key, ''), true);
            if (is_array($list)) Setting::set([$key => json_encode(Section::normalize($list, $name), JSON_UNESCAPED_UNICODE)]);
        }

        $activated = false;
        if (Request::post('_activate') && $name !== Theme::saved()) {
            Theme::activate($name);
            $activated = true;
        }
        Theme::clearDraft();
        Log::info('Tema özelleştirildi', ['theme' => $name, 'activated' => $activated, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('appearance.customized', ($activated ? "{$theme['name']} temasını etkinleştirip yayınladı" : "{$theme['name']} temasında değişiklikleri yayınladı"), 'admin/appearance/customize');

        return $this->json([
            'message'   => $activated ? "{$theme['name']} etkinleştirildi ve yayınlandı." : 'Değişiklikler yayınlandı.',
            'activated' => $activated,
        ]);
    }

    // Özelleştir bölümleri: her temada ortak "Site kimliği" + temanın kendi bölümleri (settings.php)
    private static function customizerSections(string $folder): array
    {
        $identity = [];
        foreach (['site_name', 'site_tagline', 'logo', 'favicon'] as $key) {
            if ($field = Setting::field($key)) $identity[$key] = $field;
        }
        return ['identity' => [
            'label'       => 'Site kimliği',
            'description' => 'Site adı, slogan, logo ve simge. Her temada ortaktır.',
            'fields'      => $identity,
        ]] + Theme::sections($folder);
    }

    private const AREA_LABELS = ['home' => 'Ana sayfa bölümleri'];

    // Taslak/yayında kabul edilen alanlar: panel alanları + bölüm düzenleri (tür 'sections', JSON)
    private static function customizerFields(string $folder): array
    {
        $fields = array_merge(...array_values(array_column(self::customizerSections($folder), 'fields')));
        foreach (Section::areas($folder) as $area) {
            $fields[Section::key($area, $folder)] = ['label' => self::AREA_LABELS[$area] ?? $area, 'type' => 'sections'];
        }
        return $fields;
    }

    // ---- Menüler (sayfa iskeleti; içerik API'den: app/Assets/admin/menus-page.js)

    public function menus(): View
    {
        return $this->view('admin/appearance/menus', [
            'title' => 'Menüler',
            'theme' => Theme::manifest(Theme::saved()),
        ]);
    }

    private function theme(string $folder): array
    {
        try {
            return Theme::manifest($folder);
        } catch (Throwable) {
            $this->abort(404);
        }
    }
}
