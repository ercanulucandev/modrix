<?php
namespace App\Controllers\Admin;

use App\Services\Store;
use Core\View;
use Throwable;

// Admin > Sistem > Mağaza: modüller ve temalar
class StoreController extends AdminController
{
    public function index(): View
    {
        $type = isset(Store::TYPES[$t = (string) Request::get('type', 'modules')]) ? $t : 'modules';
        $catalog = Store::catalog((bool) Request::get('refresh'), $type);
        $q = mb_strtolower(trim((string) Request::get('q', '')));
        $category = (string) Request::get('category', '');
        $items = array_values(array_filter($catalog['items'], fn($m) => ($q === '' || str_contains(mb_strtolower($m['name'] . ' ' . $m['description']), $q))
            && ($category === '' || $m['category'] === $category)));
        return $this->view('admin/store/index', [
            'title' => 'Mağaza', 'type' => $type, 'catalog' => $catalog, 'items' => $items, 'q' => $q, 'category' => $category,
            'categories' => array_values(array_unique(array_column($catalog['items'], 'category'))),
            'updates' => count(array_filter($catalog['items'], fn($m) => Store::status($m, $type)[0] === 'update')),
        ]);
    }

    public function install(string $slug): void
    {
        $type = Request::post('type') === 'themes' ? 'themes' : 'modules';
        $back = 'admin/store' . ($type === 'themes' ? '?type=themes' : '');
        try {
            $r = Store::install($slug, $type);
        } catch (Throwable $e) {
            $this->redirect($back, 'Kurulamadı: ' . $e->getMessage(), 'error');
        }
        $this->redirect($back, $r['action'] === 'updated'
            ? "{$r['folder']} v{$r['version']} sürümüne güncellendi."
            : ($type === 'themes' ? "{$r['folder']} teması kuruldu. Görünüm'den önizleyip etkinleştirebilirsiniz." : "{$r['folder']} v{$r['version']} kuruldu ve etkinleştirildi."));
    }

    public function license(string $slug): void
    {
        Store::setLicense($slug, trim((string) Request::post('license', '')));
        $this->redirect('admin/store' . (Request::post('type') === 'themes' ? '?type=themes' : ''), 'Lisans anahtarı kaydedildi.');
    }
}
