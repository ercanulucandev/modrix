<?php
namespace App\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use Core\Validator;
use Core\View;

// Admin > Kullanıcılar > Roller: rol ekle/düzenle/sil, yetkileri işaretle. "Yönetici" sistem rolüdür (her şey yetkili).
class RoleController extends AdminController
{
    public function index(): View
    {
        $counts = array_column(User::query()->select('role')->selectRaw('COUNT(*) AS total')->groupBy('role')->get(), 'total', 'role');
        return $this->view('admin/roles/index', ['title' => 'Roller', 'roles' => Role::map(), 'counts' => $counts, 'permissions' => Access::all()]);
    }

    public function create(): View
    {
        return $this->view('admin/roles/form', ['title' => 'Yeni rol', 'role' => null, 'groups' => Access::permissions()]);
    }

    public function store(): void
    {
        [$name, $permissions] = $this->input();
        $slug = $this->uniqueSlug($name);
        Role::create(['name' => $name, 'slug' => $slug, 'permissions' => json_encode($permissions)]);
        Role::forget();
        Log::info('Rol eklendi', ['role' => $slug, 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.role', "\"$name\" rolünü ekledi", 'admin/roles');
        $this->redirect('admin/roles', "\"$name\" rolü eklendi.");
    }

    public function edit(string $id): View
    {
        $role = $this->find($id);
        return $this->view('admin/roles/form', ['title' => $role['name'], 'role' => $role, 'groups' => Access::permissions()]);
    }

    public function update(string $id): void
    {
        $role = $this->find($id);
        [$name, $permissions] = $this->input();
        // Sistem rolünün yetkileri değişmez (her şey); sadece adı
        $values = ['name' => $name];
        if (!$role['is_system']) $values['permissions'] = json_encode($permissions);
        Role::update($role['id'], $values);
        Role::forget();
        Log::info('Rol güncellendi', ['role' => $role['slug'], 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.role', "\"$name\" rolünü ve yetkilerini güncelledi", 'admin/roles/' . $role['id'] . '/edit');
        $this->redirect('admin/roles', 'Rol kaydedildi.');
    }

    public function destroy(string $id): void
    {
        $role = $this->find($id);
        if ($role['is_system']) $this->back('Sistem rolü silinemez.', 'error');
        if (User::count(['role' => $role['slug']])) $this->back('Bu rolde kullanıcılar var; önce onlara başka bir rol verin.', 'error');
        Role::delete($role['id']);
        Role::forget();
        $this->redirect('admin/roles', 'Rol silindi.');
    }

    private function find(string $id): array
    {
        $row = Role::find((int) $id) ?? $this->abort(404);
        $row['permissions'] = json_decode((string) $row['permissions'], true) ?: [];
        return $row;
    }

    private function input(): array
    {
        $data = $this->validate(['name' => 'required|max:60'], ['name' => 'Rol adı']);
        $known = Access::all();
        $permissions = array_values(array_filter((array) Request::post('permissions', []), fn($p) => is_string($p) && isset($known[$p])));
        return [trim($data['name']), $permissions];
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($name, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']))), '-') ?: 'rol';
        $base = substr($base, 0, 30);
        if ($base === 'user') $base = 'user-role';   // 'user' site üyeleri için ayrılmış
        $slug = $base;
        for ($i = 2; isset(Role::map()[$slug]); $i++) $slug = "$base-$i";
        return $slug;
    }
}
