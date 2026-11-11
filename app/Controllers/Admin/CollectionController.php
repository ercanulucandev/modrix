<?php
namespace App\Controllers\Admin;

use App\Services\Access;
use App\Services\Activity;
use App\Services\Collection;
use Core\View;

// Admin > (modülün koleksiyonu): /admin/collections/{ad} liste, ekle, düzenle, sil, sırala, gizle/göster
class CollectionController extends AdminController
{
    public function index(string $name): View
    {
        $def = $this->def($name);
        return $this->view('admin/collections/index', ['title' => $def['label'], 'name' => $name, 'def' => $def, 'items' => Collection::items($name, null, true)]);
    }

    public function create(string $name): View
    {
        $def = $this->def($name);
        return $this->view('admin/collections/form', ['title' => 'Yeni ' . mb_strtolower($def['singular']), 'name' => $name, 'def' => $def, 'item' => null]);
    }

    public function edit(string $name, string $id): View
    {
        $def = $this->def($name);
        $item = Collection::find($name, (int) $id) ?? $this->abort(404);
        return $this->view('admin/collections/form', ['title' => $item['title'], 'name' => $name, 'def' => $def, 'item' => $item]);
    }

    public function store(string $name): void
    {
        $def = $this->def($name);
        $item = Collection::save($name);
        Activity::log('collection.created', "{$def['label']}: \"{$item['title']}\" ekledi", "admin/collections/$name/{$item['id']}/edit");
        $this->redirect(Request::post('_next') === 'new' ? "admin/collections/$name/create" : "admin/collections/$name", "\"{$item['title']}\" eklendi.");
    }

    public function update(string $name, string $id): void
    {
        $def = $this->def($name);
        $item = Collection::find($name, (int) $id) ?? $this->abort(404);
        $item = Collection::save($name, $item);
        Activity::log('collection.updated', "{$def['label']}: \"{$item['title']}\" güncelledi", "admin/collections/$name/{$item['id']}/edit");
        $this->redirect("admin/collections/$name/{$item['id']}/edit", 'Kaydedildi.');
    }

    public function destroy(string $name, string $id): void
    {
        $def = $this->def($name);
        $item = Collection::find($name, (int) $id) ?? $this->abort(404);
        Collection::delete($name, $item);
        Activity::log('collection.deleted', "{$def['label']}: \"{$item['title']}\" sildi");
        $this->redirect("admin/collections/$name", "\"{$item['title']}\" silindi.");
    }

    // JSON: { ids: [3, 1, 2] }
    public function sort(string $name): array
    {
        $this->def($name);
        Collection::sort($name, (array) Request::post('ids', []));
        return ['message' => 'Sıra kaydedildi.'];
    }

    public function toggle(string $name, string $id): void
    {
        $this->def($name);
        $item = Collection::find($name, (int) $id) ?? $this->abort(404);
        Collection::setVisible($name, $item['id'], !$item['visible']);
        $this->redirect("admin/collections/$name", $item['visible'] ? "\"{$item['title']}\" gizlendi." : "\"{$item['title']}\" yayında.");
    }

    private function def(string $name): array
    {
        $def = Collection::def($name) ?? $this->abort(404, 'Koleksiyon bulunamadı (modülü kapalı olabilir).');
        if (!Access::can($def['permission'])) $this->abort(403);
        return $def;
    }
}
