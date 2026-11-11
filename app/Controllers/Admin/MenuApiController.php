<?php
namespace App\Controllers\Admin;

use Core\Database;
use Core\Hook;
use Core\Menu;
use Core\Theme;
use Core\Validator;
use RuntimeException;

// Görünüm > Menüler API'si (app/Assets/admin/menus-page.js kullanır). Hepsi JSON.
//   GET    /admin/api/menus              menüler + etkin temanın konumları + eklenebilecek hazır bağlantılar
//   POST   /admin/api/menus              { name }                         yeni menü
//   GET    /admin/api/menus/{id}         menü + öğeleri (ağaç)
//   PUT    /admin/api/menus/{id}         { name, items: [{label, url, new_tab, children}] }   hepsini kaydeder
//   DELETE /admin/api/menus/{id}
//   PUT    /admin/api/menus/locations    { header: 3, footer: null }
class MenuApiController extends AdminController
{
    public function index(): string
    {
        $menus = Database::table('menus as m')
            ->select('m.id', 'm.name', 'm.updated_at')
            ->selectRaw('(SELECT COUNT(*) FROM menu_items i WHERE i.menu_id = m.id) AS items')
            ->orderBy('m.name')->get();

        return $this->json([
            'menus'     => array_map(fn($m) => ['id' => (int) $m['id'], 'name' => $m['name'], 'items' => (int) $m['items']], $menus),
            'locations' => Menu::locations(),
            'theme'     => Theme::manifest(Theme::saved())['name'],
            'links'     => self::links(),
        ]);
    }

    public function store(): string
    {
        $name = $this->name();
        $id = Database::table('menus')->insertGetId(['name' => $name, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        // İlk menü boş bir konuma kendiliğinden atansın (yeni kullanıcı için)
        foreach (Menu::locations() as $key => $location) {
            if (!$location['menu_id']) { Menu::assign([$key => $id]); break; }
        }
        return $this->json(['menu' => $this->menu($id)], 201);
    }

    public function show(string $id): string
    {
        return $this->json(['menu' => $this->menu((int) $id)]);
    }

    public function update(string $id): string
    {
        $id = (int) $id;
        $this->menu($id);
        $name = $this->name();

        try {
            $items = Menu::cleanTree(Request::post('items', []));
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        Database::table('menus')->where('id', $id)->update(['name' => $name]);
        Menu::replaceItems($id, $items);
        Log::info('Menü kaydedildi', ['menu' => $id, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('appearance.menu', '"' . $name . '" menüsünü kaydetti', 'admin/appearance/menus?menu=' . $id);
        return $this->json(['menu' => $this->menu($id)]);
    }

    public function destroy(string $id): string
    {
        $id = (int) $id;
        $this->menu($id);
        Database::table('menus')->where('id', $id)->delete();   // öğeler FOREIGN KEY ile silinir

        // Bu menünün atandığı konumları boşalt
        $assigned = array_filter(Menu::locations(), fn($l) => $l['menu_id'] === $id);
        if ($assigned) Menu::assign(array_map(fn() => null, $assigned));

        return $this->json(['ok' => true]);
    }

    public function locations(): string
    {
        $values = Request::post('locations', []);
        if (!is_array($values)) return $this->json(['error' => 'Geçersiz istek'], 422);
        try {
            Menu::assign($values);
        } catch (RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
        return $this->json(['locations' => Menu::locations()]);
    }

    // ---- iç

    private function menu(int $id): array
    {
        $menu = Database::table('menus')->where('id', $id)->first() ?? $this->abort(404, 'Menü bulunamadı');
        return ['id' => (int) $menu['id'], 'name' => $menu['name'], 'items' => Menu::items($id) ?? []];
    }

    private function name(): string
    {
        $name = trim((string) Request::post('name', ''));
        if ($name === '' || mb_strlen($name) > 100) Validator::fail(['name' => 'Menü adı 1-100 karakter olmalı.']);
        return $name;
    }

    // Menüye tek tıkla eklenebilecek bağlantılar: [['group' => 'Sayfalar', 'label' => 'Ana sayfa', 'url' => '/'], ...]
    // Modüller ekler: Hook::on('menu.links', fn(array $l) => [...$l, ['group' => 'Blog', 'label' => '...', 'url' => 'blog/...']])
    private static function links(): array
    {
        $links = [['group' => 'Genel', 'label' => 'Ana sayfa', 'url' => '/']];
        foreach (Hook::filter('site.menu', []) as $item) {
            if (!empty($item['url']) && !empty($item['label'])) $links[] = ['group' => 'Genel', 'label' => $item['label'], 'url' => $item['url']];
        }
        $links = Hook::filter('menu.links', $links);
        return array_values(array_filter($links, fn($l) => !empty($l['label']) && Menu::validUrl((string) ($l['url'] ?? ''))));
    }
}
