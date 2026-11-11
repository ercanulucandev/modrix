<?php
namespace App\Controllers\Admin;

use App\Models\Redirect;
use Core\Database;
use Core\Validator;
use Core\View;

// Admin > SEO: yönlendirmeler ve bulunamayan adresler (404 kaydı)
class SeoController extends AdminController
{
    public function redirects(): View
    {
        $q = trim((string) Request::get('q', ''));
        $list = Redirect::query()->search(['source', 'target'], $q)->orderByDesc('id')->paginate(30);
        return $this->view('admin/seo/redirects', [
            'title'     => 'Yönlendirmeler',
            'redirects' => $list,
            'q'         => $q,
            'source'    => (string) Request::get('source', ''),
            'notFound'  => Database::table('not_found_log')->count(),
        ]);
    }

    public function store(): void
    {
        [$source, $target, $code] = $this->input();
        if (Redirect::query()->where('source', $source)->exists()) Validator::fail(['source' => 'Bu adres için zaten bir yönlendirme var.']);
        Redirect::create(['source' => $source, 'target' => $target, 'code' => $code, 'is_auto' => 0]);
        Database::table('not_found_log')->where('path', $source)->delete();
        Log::info('Yönlendirme eklendi', ['source' => $source, 'target' => $target, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('seo.redirect', "Yönlendirme ekledi: /$source → $target", 'admin/seo');
        $this->redirect('admin/seo', 'Yönlendirme eklendi.');
    }

    public function update(string $id): void
    {
        $row = Redirect::find((int) $id) ?? $this->abort(404);
        [$source, $target, $code] = $this->input();
        if (Redirect::query()->where('source', $source)->where('id', '!=', $row['id'])->exists()) Validator::fail(['source' => 'Bu adres için zaten bir yönlendirme var.']);
        Redirect::update($row['id'], ['source' => $source, 'target' => $target, 'code' => $code]);
        $this->back('Yönlendirme kaydedildi.');
    }

    public function destroy(string $id): void
    {
        Redirect::delete((int) $id);
        $this->back('Yönlendirme silindi.');
    }

    public function notFound(): View
    {
        $list = Database::table('not_found_log')->orderByDesc('hits')->orderByDesc('last_seen')->paginate(50);
        return $this->view('admin/seo/not-found', ['title' => 'Bulunamayan adresler', 'rows' => $list]);
    }

    public function forget(string $id): void
    {
        Database::table('not_found_log')->where('id', (int) $id)->delete();
        $this->back('Kayıt silindi.');
    }

    public function clear(): void
    {
        Database::table('not_found_log')->whereRaw('1 = 1')->delete();
        $this->redirect('admin/seo/not-found', 'Bulunamayan adres kayıtları temizlendi.');
    }

    private function input(): array
    {
        $data = $this->validate(
            ['source' => 'required|max:500', 'target' => 'required|max:500', 'code' => 'required|in:301,302'],
            ['source' => 'Eski adres', 'target' => 'Yeni adres', 'code' => 'Tür']
        );
        $source = Redirect::normalize($data['source']);
        $target = trim($data['target']);
        if (!preg_match('#^https?://#i', $target)) $target = Redirect::normalize($target) ?: '/';

        $errors = [];
        if ($source === '' || preg_match('#^(admin|api|assets|theme|media)(/|$)#', $source)) $errors['source'] = 'Bu adres yönlendirilemez.';
        if (!Redirect::validTarget($target)) $errors['target'] = 'Geçersiz adres.';
        if ($source !== '' && $source === $target) $errors['target'] = 'Adres kendisine yönlendirilemez.';
        if ($errors) Validator::fail($errors);
        return [$source, $target, (int) $data['code']];
    }
}
