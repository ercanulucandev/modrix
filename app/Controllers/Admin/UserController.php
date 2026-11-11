<?php
namespace App\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\PasswordReset;
use Core\Validator;
use Core\View;

// Admin > Kullanıcılar: listele, ekle (şifre ya da davet e-postası), düzenle, pasife al, sil.
// Kurallar: kendi hesabınızın rolünü/durumunu buradan değiştiremez, kendinizi silemezsiniz (Profil sayfası var);
// her zaman en az bir aktif "her şey yetkili" (Yönetici) kullanıcı kalır.
class UserController extends AdminController
{
    public function index(): View
    {
        $q = trim((string) Request::get('q', ''));
        $role = (string) Request::get('role', '');
        $users = User::query()
            ->search(['name', 'email'], $q)
            ->when($role !== '', fn($query) => $query->where('role', $role))
            ->orderBy('name')->paginate(25);

        return $this->view('admin/users/index', ['title' => 'Kullanıcılar', 'users' => $users, 'roles' => self::roleOptions(), 'q' => $q, 'role' => $role]);
    }

    public function create(): View
    {
        return $this->view('admin/users/form', ['title' => 'Yeni kullanıcı', 'user' => null, 'roles' => self::roleOptions()]);
    }

    public function store(): void
    {
        $data = $this->validate([
            'name'     => 'required|max:100',
            'email'    => 'required|email|max:190|unique:users,email',
            'role'     => 'required|in:' . implode(',', array_keys(self::roleOptions())),
            'password' => 'nullable|min:8|max:200',
        ], ['name' => 'Ad', 'email' => 'E-posta', 'role' => 'Rol', 'password' => 'Şifre']);

        $invite = empty($data['password']);
        $id = User::register([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'status'   => 'active',
            'password' => $invite ? bin2hex(random_bytes(24)) : $data['password'],   // davette rastgele: kullanıcı kendisi belirler
        ]);
        Log::info('Kullanıcı eklendi', ['user' => $id, 'role' => $data['role'], 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.created', "Kullanıcı ekledi: {$data['name']} ({$data['email']})", "admin/users/$id/edit");

        $message = 'Kullanıcı eklendi.';
        if ($invite) {
            $message = PasswordReset::send(User::find($id), true)
                ? 'Kullanıcı eklendi; şifresini belirlemesi için davet e-postası gönderildi.'
                : 'Kullanıcı eklendi ama davet e-postası gönderilemedi (' . Mail::lastError() . '). E-posta ayarlarını kontrol edip "Davet gönder" ile tekrar deneyin.';
        }
        $this->redirect('admin/users', $message, $invite && str_contains($message, 'gönderilemedi') ? 'error' : 'ok');
    }

    public function edit(string $id): View
    {
        $user = User::find((int) $id) ?? $this->abort(404);
        return $this->view('admin/users/form', ['title' => $user['name'], 'user' => $user, 'roles' => self::roleOptions()]);
    }

    public function update(string $id): void
    {
        $user = User::find((int) $id) ?? $this->abort(404);
        $data = $this->validate([
            'name'     => 'required|max:100',
            'email'    => 'required|email|max:190|unique:users,email,' . $user['id'],
            'role'     => 'required|in:' . implode(',', array_keys(self::roleOptions())),
            'status'   => 'required|in:active,passive',
            'password' => 'nullable|min:8|max:200',
        ], ['name' => 'Ad', 'email' => 'E-posta', 'role' => 'Rol', 'status' => 'Durum', 'password' => 'Şifre']);

        $self = (int) $user['id'] === (int) $this->admin()['id'];
        if ($self && ($data['role'] !== $user['role'] || $data['status'] !== 'active')) {
            Validator::fail(['role' => 'Kendi rolünüzü ve durumunuzu değiştiremezsiniz; başka bir yöneticiden isteyin.']);
        }
        $losesSuper = Access::isSuperRole($user['role']) && (!Access::isSuperRole($data['role']) || $data['status'] !== 'active');
        if ($losesSuper && self::otherActiveSupers($user['id']) === 0) {
            Validator::fail(['role' => 'En az bir aktif Yönetici kalmalı.']);
        }

        $values = ['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'role' => $data['role'], 'status' => $data['status']];
        if (!empty($data['password'])) $values['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        User::update($user['id'], $values);
        Log::info('Kullanıcı güncellendi', ['user' => $user['id'], 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.updated', "Kullanıcıyı güncelledi: {$data['name']}" . ($data['role'] !== $user['role'] ? " (rol: {$user['role']} → {$data['role']})" : '') . ($data['status'] !== $user['status'] ? " (durum: {$data['status']})" : ''), "admin/users/{$user['id']}/edit");
        $this->redirect('admin/users', 'Kullanıcı kaydedildi.');
    }

    public function invite(string $id): void
    {
        $user = User::find((int) $id) ?? $this->abort(404);
        $ok = PasswordReset::send($user, true);
        $this->back($ok ? "Şifre belirleme bağlantısı gönderildi: {$user['email']}" : 'Gönderilemedi: ' . Mail::lastError(), $ok ? 'ok' : 'error');
    }

    public function resetTwoFactor(string $id): void
    {
        $user = User::find((int) $id) ?? $this->abort(404);
        \App\Services\TwoFactor::disable($user['id']);
        Log::warning('Kullanıcının iki adımlı doğrulaması sıfırlandı', ['user' => $user['id'], 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.2fa', "İki adımlı doğrulamayı sıfırladı: {$user['name']}", "admin/users/{$user['id']}/edit");
        $this->redirect("admin/users/{$user['id']}/edit", 'İki adımlı doğrulama sıfırlandı.');
    }

    public function destroy(string $id): void
    {
        $user = User::find((int) $id) ?? $this->abort(404);
        if ((int) $user['id'] === (int) $this->admin()['id']) $this->back('Kendi hesabınızı silemezsiniz.', 'error');
        if (Access::isSuperRole($user['role']) && self::otherActiveSupers($user['id']) === 0) $this->back('En az bir aktif Yönetici kalmalı.', 'error');
        User::delete($user['id']);
        PasswordReset::forget($user['email']);
        Log::warning('Kullanıcı silindi', ['user' => $user['id'], 'email' => $user['email'], 'admin' => $this->admin()['id']]);
        \App\Services\Activity::log('user.deleted', "Kullanıcıyı sildi: {$user['name']} ({$user['email']})");
        $this->redirect('admin/users', 'Kullanıcı silindi.');
    }

    // Panel rolleri + panele giremeyen site üyesi
    private static function roleOptions(): array
    {
        return Role::options() + ['user' => 'Site üyesi (panele giremez)'];
    }

    private static function otherActiveSupers(int $exceptId): int
    {
        $supers = array_keys(array_filter(Role::map(), fn($r) => in_array('*', $r['permissions'], true)));
        return User::query()->whereIn('role', $supers)->where('status', 'active')->where('id', '!=', $exceptId)->count();
    }
}
