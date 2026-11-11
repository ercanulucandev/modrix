<?php
namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\Activity;
use App\Services\TwoFactor;
use Core\Validator;
use Core\View;

// Admin > Profilim > Güvenlik: iki adımlı doğrulamayı açma/kapatma, kurtarma kodları
class SecurityController extends AdminController
{
    public function index(): View
    {
        $user = $this->admin();
        $enabled = TwoFactor::enabled($user);

        // Kurulum: gizli anahtar oturumda bekler, kod doğrulanınca kaydedilir
        $secret = null;
        if (!$enabled) {
            $secret = Session::get('two_factor_setup') ?: TwoFactor::newSecret();
            Session::set('two_factor_setup', $secret);
        }

        return $this->view('admin/profile-security', [
            'title'     => 'Güvenlik',
            'user'      => $user,
            'enabled'   => $enabled,
            'required'  => TwoFactor::required(),
            'secret'    => $secret,
            'uri'       => $secret ? TwoFactor::uri($secret, $user['email']) : null,
            'remaining' => $enabled ? TwoFactor::remainingRecovery($user) : 0,
            'codes'     => Session::flash('recovery_codes'),
        ]);
    }

    public function enable(): void
    {
        $user = $this->admin();
        $secret = Session::get('two_factor_setup');
        if (TwoFactor::enabled($user) || !$secret) $this->redirect('admin/profile/security');

        $step = TwoFactor::check($secret, (string) Request::post('code', ''));
        if ($step === null) Validator::fail(['code' => 'Kod hatalı. Telefonunuzun saatinin doğru olduğundan emin olup yeni kodu girin.']);

        $codes = TwoFactor::enable($user['id'], $secret, $step);
        Session::forget('two_factor_setup');
        Session::flash('recovery_codes', $codes);
        Activity::log('auth.2fa', 'İki adımlı doğrulamayı açtı', 'admin/profile/security');
        $this->redirect('admin/profile/security', 'İki adımlı doğrulama açıldı. Kurtarma kodlarınızı şimdi kaydedin.');
    }

    public function recovery(): void
    {
        $user = $this->confirmPassword();
        Session::flash('recovery_codes', TwoFactor::regenerateRecovery($user['id']));
        Activity::log('auth.2fa', 'Yeni kurtarma kodları oluşturdu', 'admin/profile/security');
        $this->redirect('admin/profile/security', 'Yeni kurtarma kodları oluşturuldu; eskileri artık geçmez.');
    }

    public function disable(): void
    {
        $user = $this->confirmPassword();
        if (TwoFactor::required()) $this->redirect('admin/profile/security', 'İki adımlı doğrulama bu sitede zorunlu; kapatılamaz.', 'error');
        TwoFactor::disable($user['id']);
        Log::warning('İki adımlı doğrulama kapatıldı', ['user' => $user['id'], 'ip' => Request::ip()]);
        Activity::log('auth.2fa', 'İki adımlı doğrulamayı kapattı', 'admin/profile/security');
        $this->redirect('admin/profile/security', 'İki adımlı doğrulama kapatıldı.', 'info');
    }

    private function confirmPassword(): array
    {
        $user = $this->admin();
        if (!TwoFactor::enabled($user)) $this->redirect('admin/profile/security');
        if (!password_verify((string) Request::post('password', ''), $user['password'])) {
            Validator::fail(['password' => 'Şifreniz hatalı.']);
        }
        return $user;
    }
}
