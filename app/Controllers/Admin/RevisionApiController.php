<?php
namespace App\Controllers\Admin;

use App\Services\Access;
use App\Services\Revision;
use Core\Html;

// İçerik geçmişi API'si: /admin/api/revisions
//   GET /{type}/{id}   sürümler (en yeni önce)  -> { data: [{id, user, title, created_at, words}] }
//   GET /{id}          tek sürüm                -> { data: {id, ..., fields: {...}} }
class RevisionApiController extends AdminController
{
    public function index(string $type, string $id): array
    {
        $this->authorize($type);
        return ['data' => array_map(function ($r) {
            $data = json_decode($r['data'], true) ?: [];
            return [
                'id'         => (int) $r['id'],
                'user'       => $r['user_name'],
                'title'      => $r['title'],
                'created_at' => $r['created_at'],
                'words'      => str_word_count(Html::text($data['body'] ?? ''), 0, 'çğıöşüÇĞİÖŞÜ0123456789'),
            ];
        }, Revision::list($type, (int) $id))];
    }

    public function show(string $id): array
    {
        $rev = Revision::find((int) $id) ?? $this->abort(404, 'Sürüm bulunamadı');
        $this->authorize($rev['type']);
        return ['data' => [
            'id'         => (int) $rev['id'],
            'user'       => $rev['user_name'],
            'created_at' => $rev['created_at'],
            'fields'     => $rev['data'],
            'html'       => Html::render($rev['data']['body'] ?? ''),
        ]];
    }

    private function authorize(string $type): void
    {
        $def = Revision::type($type) ?? $this->abort(404, 'Bilinmeyen içerik türü');
        if (!Access::can($def['permission'])) $this->abort(403);
    }
}
