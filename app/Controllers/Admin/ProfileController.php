<?php
namespace App\Controllers\Admin;

use App\Models\User;
use Core\Validator;
use Core\View;

// Admin > Profilim: kendi adınız, e-postanız ve şifreniz. E-posta ya da şifre değişiminde mevcut şifre istenir.
class ProfileController extends AdminController
{
    public function edit(): View
    {
        return $this->view('admin/profile', ['title' => 'Profilim', 'user' => $this->admin()]);
    }

    public function update(): void
    {
        $user = $this->admin();
        $data = $this->validate([
            'name'     => 'required|max:100',
            'email'    => 'required|email|max:190|unique:users,email,' . $user['id'],
            'password' => 'nullable|min:8|max:200|confirmed',
        ], ['name' => 'Ad', 'email' => 'E-posta', 'password' => 'Yeni şifre']);

        $email = mb_strtolower($data['email']);
        $sensitive = $email !== $user['email'] || !empty($data['password']);
        if ($sensitive && !password_verify((string) Request::post('current_password', ''), $user['password'])) {
            Validator::fail(['current_password' => 'Mevcut şifreniz hatalı.']);
        }

        $values = ['name' => $data['name'], 'email' => $email];
        if (!empty($data['password'])) $values['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        User::update($user['id'], $values);
        if ($sensitive) Log::warning('Admin e-postasını/şifresini değiştirdi', ['user' => $user['id'], 'ip' => Request::ip()]);
        \App\Services\Activity::log('auth.profile', $sensitive ? 'Profilini güncelledi (e-posta/şifre dahil)' : 'Profilini güncelledi');
        $this->redirect('admin/profile', 'Bilgileriniz kaydedildi.');
    }
}
