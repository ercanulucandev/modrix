<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\Access;
use App\Services\PasswordReset;
use Core\View;

// Admin > Şifremi unuttum: e-postaya tek kullanımlık bağlantı, bağlantıdan yeni şifre.
// Güvenlik: e-postanın kayıtlı olup olmadığı belli edilmez; anahtarın sadece özeti saklanır;
// aynı adrese dakikada en fazla bir e-posta. Yeni kullanıcı davetleri de aynı bağlantıyı kullanır (PasswordReset).
class PasswordController extends AdminController
{
    protected ?string $layout = 'admin-auth';

    public function forgotForm(): View
    {
        return $this->view('admin/auth/forgot', ['title' => 'Şifremi unuttum']);
    }

    public function sendLink(): void
    {
        $data = $this->validate(['email' => 'required|email|max:190'], ['email' => 'E-posta']);
        $email = mb_strtolower(trim($data['email']));
        $user = User::findByEmail($email);

        if ($user && Access::isPanelRole($user['role']) && $user['status'] === 'active' && !PasswordReset::recentlySent($email)) {
            $ok = PasswordReset::send($user);
            Log::info('Şifre sıfırlama bağlantısı istendi', ['email' => $email, 'ip' => Request::ip(), 'sent' => $ok]);
        } elseif (!$user) {
            Log::info('Kayıtlı olmayan e-postaya şifre sıfırlama istendi', ['email' => $email, 'ip' => Request::ip()]);
        }

        // Her durumda aynı yanıt (hangi adreslerin kayıtlı olduğu öğrenilemesin)
        $this->redirect('admin/login', 'Bu adres kayıtlıysa şifre sıfırlama bağlantısı gönderildi. Gelen kutunuzu (ve spam klasörünü) kontrol edin.', 'info');
    }

    public function resetForm(string $token): View
    {
        $email = (string) Request::get('email', '');
        if (!PasswordReset::valid($email, $token)) $this->redirect('admin/forgot', 'Bağlantı geçersiz ya da süresi dolmuş. Yeni bağlantı isteyin.', 'error');
        return $this->view('admin/auth/reset', ['title' => 'Yeni şifre', 'token' => $token, 'email' => $email]);
    }

    public function reset(): void
    {
        $email = mb_strtolower(trim((string) Request::post('email', '')));
        $token = (string) Request::post('token', '');
        if (!PasswordReset::valid($email, $token)) $this->redirect('admin/forgot', 'Bağlantı geçersiz ya da süresi dolmuş. Yeni bağlantı isteyin.', 'error');

        $data = $this->validate(['password' => 'required|min:8|max:200|confirmed'], ['password' => 'Şifre']);
        $user = User::findByEmail($email) ?? $this->abort(404);
        User::update($user['id'], ['password' => password_hash($data['password'], PASSWORD_DEFAULT)]);
        PasswordReset::forget($email);

        Log::warning('Admin şifresi belirlendi/sıfırlandı', ['user' => $user['id'], 'ip' => Request::ip()]);
        \App\Services\Activity::log('auth.password_reset', 'Şifresini e-postadaki bağlantıyla belirledi', null, $user);
        $this->redirect('admin/login', 'Şifreniz kaydedildi. Yeni şifrenizle giriş yapabilirsiniz.');
    }
}
