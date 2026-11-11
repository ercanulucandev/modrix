<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\Activity;
use App\Services\TwoFactor;
use Core\View;

// Giriş: e-posta + şifre; iki adımlı doğrulama açıksa ikinci adımda uygulama kodu ya da kurtarma kodu
class AuthController extends AdminController
{
    private const CHALLENGE_TTL = 300;      // ikinci adım için 5 dakika
    private const CHALLENGE_ATTEMPTS = 5;   // yanlış koddan sonra baştan giriş

    protected ?string $layout = 'admin-auth';

    public function form(): View
    {
        Session::forget('two_factor_login');
        return $this->view('admin/auth/login', ['title' => 'Yönetim girişi']);
    }

    public function login(): void
    {
        $data = $this->validate(
            ['email' => 'required|email', 'password' => 'required'],
            ['email' => 'E-posta', 'password' => 'Şifre']
        );

        $user = User::checkAdmin($data['email'], (string) Request::post('password'));
        if (!$user) {
            Log::warning('Başarısız admin girişi', ['email' => $data['email'], 'ip' => Request::ip()]);
            Activity::log('auth.failed', 'Başarısız giriş denemesi: ' . $data['email'], null, []);
            Session::flash('old', ['email' => $data['email']]);
            $this->redirect('admin/login', 'E-posta veya şifre hatalı.', 'error');
        }

        if (TwoFactor::enabled($user)) {
            Session::regenerate();
            Session::set('two_factor_login', ['id' => (int) $user['id'], 'at' => time(), 'attempts' => 0]);
            $this->redirect('admin/login/verify');
        }

        $this->complete($user);
    }

    public function challengeForm(): View
    {
        $this->challenge();
        return $this->view('admin/auth/two-factor', ['title' => 'İki adımlı doğrulama']);
    }

    public function challengeVerify(): void
    {
        [$pending, $user] = $this->challenge();
        $code = trim((string) Request::post('code', ''));

        if ($code === '' || !TwoFactor::verify($user, $code)) {
            $pending['attempts']++;
            Activity::log('auth.failed', 'Hatalı doğrulama kodu girildi', null, $user);
            if ($pending['attempts'] >= self::CHALLENGE_ATTEMPTS) {
                Session::forget('two_factor_login');
                $this->redirect('admin/login', 'Çok fazla hatalı kod. Baştan giriş yapın.', 'error');
            }
            Session::set('two_factor_login', $pending);
            $this->redirect('admin/login/verify', 'Kod hatalı ya da süresi geçmiş.', 'error');
        }

        Session::forget('two_factor_login');
        $this->complete($user);
    }

    public function logout(): void
    {
        Activity::log('auth.logout', 'Çıkış yaptı');
        User::logoutAdmin();
        $this->redirect('admin/login', 'Çıkış yaptınız.', 'info');
    }

    private function complete(array $user): never
    {
        User::loginAdmin($user);
        Activity::log('auth.login', 'Giriş yaptı', null, $user);
        $this->redirect('admin');
    }

    // Şifresi doğrulanmış, kodu beklenen kullanıcı (yoksa ya da süresi geçtiyse girişe döner)
    private function challenge(): array
    {
        $pending = Session::get('two_factor_login');
        $user = is_array($pending) && time() - $pending['at'] < self::CHALLENGE_TTL ? User::find($pending['id']) : null;
        if (!$user || !TwoFactor::enabled($user) || $user['status'] !== 'active') {
            Session::forget('two_factor_login');
            $this->redirect('admin/login', 'Doğrulama süresi doldu. Tekrar giriş yapın.', 'info');
        }
        return [$pending, $user];
    }
}
