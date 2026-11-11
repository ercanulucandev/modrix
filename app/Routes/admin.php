<?php
// Admin paneli route'ları: hepsi /admin ile başlar ('web' grubunda, CSRF korumalı).
// Modüller kendi admin sayfalarını routes.php içinde ekler (bkz. app/Modules/Blog/routes.php).
// Yetkiler: 'admin' panele giriş, 'can:...' role göre (Admin > Kullanıcılar > Roller; bkz. app/Services/Access.php)
use App\Controllers\Admin\ActivityController;
use App\Controllers\Admin\AppearanceController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\CollectionController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\LanguageController;
use App\Controllers\Admin\MediaApiController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\MenuApiController;
use App\Controllers\Admin\ModuleController;
use App\Controllers\Admin\PasswordController;
use App\Controllers\Admin\ProfileController;
use App\Controllers\Admin\RevisionApiController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SecurityController;
use App\Controllers\Admin\SeoController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\StoreController;
use App\Controllers\Admin\UpdateController;
use App\Controllers\Admin\UserController;

// Giriş ve şifre sıfırlama (girmiş admin panele yönlenir)
Router::group(['middleware' => ['admin.guest']], function () {
    Router::get('/login', [AuthController::class, 'form'])->name('admin.login');
    Router::post('/login', [AuthController::class, 'login']);
    Router::get('/login/verify', [AuthController::class, 'challengeForm'])->name('admin.login.verify');
    Router::post('/login/verify', [AuthController::class, 'challengeVerify']);
    Router::get('/forgot', [PasswordController::class, 'forgotForm'])->name('admin.forgot');
    Router::post('/forgot', [PasswordController::class, 'sendLink']);
    Router::get('/reset/{token}', [PasswordController::class, 'resetForm'])->name('admin.reset.form');
    Router::post('/reset', [PasswordController::class, 'reset'])->name('admin.reset');
});

Router::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

// Panel
Router::group(['middleware' => ['admin']], function () {
    // Herkes (panel rolü olan her kullanıcı)
    Router::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    Router::get('/profile', [ProfileController::class, 'edit'])->name('admin.profile');
    Router::post('/profile', [ProfileController::class, 'update']);
    // İçerik geçmişi (yetkiyi içerik türüne göre controller denetler)
    Router::get('/api/revisions/{type}/{id}', [RevisionApiController::class, 'index'])->name('admin.api.revisions');
    Router::get('/api/revisions/{id}', [RevisionApiController::class, 'show'])->name('admin.api.revisions.show');
    Router::get('/profile/security', [SecurityController::class, 'index'])->name('admin.security');
    Router::post('/profile/security/enable', [SecurityController::class, 'enable'])->name('admin.security.enable');
    Router::post('/profile/security/recovery', [SecurityController::class, 'recovery'])->name('admin.security.recovery');
    Router::post('/profile/security/disable', [SecurityController::class, 'disable'])->name('admin.security.disable');

    // Medya: kütüphaneden seçme ve yükleme her panel kullanıcısına açık (editör, özelleştir, form alanları kullanır);
    // kütüphane sayfası, düzenleme ve silme 'media.manage' ister
    Router::group(['prefix' => '/api/media'], function () {
        Router::get('/', [MediaApiController::class, 'index'])->name('admin.api.media.index');
        Router::post('/', [MediaApiController::class, 'store'])->name('admin.api.media.store');
        Router::get('/by-path', [MediaApiController::class, 'byPath'])->name('admin.api.media.byPath');
        Router::get('/{id}', [MediaApiController::class, 'show'])->name('admin.api.media.show');
    });
    Router::group(['middleware' => ['can:media.manage']], function () {
        Router::get('/media', [MediaController::class, 'index'])->name('admin.media');
        Router::group(['prefix' => '/api/media'], function () {
            Router::post('/bulk', [MediaApiController::class, 'bulk'])->name('admin.api.media.bulk');
            Router::post('/download', [MediaApiController::class, 'download'])->name('admin.api.media.download');
            Router::put('/{id}', [MediaApiController::class, 'update'])->name('admin.api.media.update');
            Router::post('/{id}/edit', [MediaApiController::class, 'edit'])->name('admin.api.media.edit');
            Router::delete('/{id}', [MediaApiController::class, 'destroy'])->name('admin.api.media.destroy');
        });
    });

    // Modüller
    Router::group(['middleware' => ['can:modules.manage']], function () {
        Router::get('/modules', [ModuleController::class, 'index'])->name('admin.modules');
        Router::get('/store', [StoreController::class, 'index'])->name('admin.store');
        Router::post('/store/{slug}/install', [StoreController::class, 'install'])->name('admin.store.install');
        Router::post('/store/{slug}/license', [StoreController::class, 'license'])->name('admin.store.license');
        Router::post('/modules/upload', [ModuleController::class, 'upload'])->name('admin.modules.upload');
        foreach (['install', 'update', 'enable', 'disable', 'uninstall', 'delete'] as $action) {
            Router::post("/modules/{name}/$action", [ModuleController::class, $action])->name("admin.modules.$action");
        }
        Router::get('/modules/{name}/icon', [ModuleController::class, 'icon'])->name('admin.modules.icon');
        Router::get('/modules/{name}/settings', [ModuleController::class, 'settings'])->name('admin.modules.settings');
        Router::post('/modules/{name}/settings', [ModuleController::class, 'saveSettings']);
    });

    // Görünüm: temalar, Özelleştir, menüler
    Router::group(['middleware' => ['can:appearance.manage']], function () {
        Router::group(['prefix' => '/appearance'], function () {
            Router::get('/', [AppearanceController::class, 'index'])->name('admin.appearance');
            Router::post('/themes/{name}/activate', [AppearanceController::class, 'activate'])->name('admin.appearance.activate');
            Router::post('/themes/{name}/preview', [AppearanceController::class, 'preview'])->name('admin.appearance.preview');
            Router::post('/themes/{name}/delete', [AppearanceController::class, 'destroy'])->name('admin.appearance.delete');
            Router::post('/preview/stop', [AppearanceController::class, 'stopPreview'])->name('admin.appearance.preview.stop');
            Router::get('/themes/{name}/screenshot', [AppearanceController::class, 'screenshot'])->name('admin.appearance.screenshot');
            Router::get('/customize', [AppearanceController::class, 'customize'])->name('admin.appearance.customize');
            Router::post('/customize/{name}/draft', [AppearanceController::class, 'draft'])->name('admin.appearance.customize.draft');
            Router::post('/customize/{name}/publish', [AppearanceController::class, 'publish'])->name('admin.appearance.customize.publish');
            Router::get('/menus', [AppearanceController::class, 'menus'])->name('admin.appearance.menus');
        });
        Router::group(['prefix' => '/api/menus'], function () {
            Router::get('/', [MenuApiController::class, 'index'])->name('admin.api.menus.index');
            Router::post('/', [MenuApiController::class, 'store'])->name('admin.api.menus.store');
            Router::put('/locations', [MenuApiController::class, 'locations'])->name('admin.api.menus.locations');
            Router::get('/{id}', [MenuApiController::class, 'show'])->name('admin.api.menus.show');
            Router::put('/{id}', [MenuApiController::class, 'update'])->name('admin.api.menus.update');
            Router::delete('/{id}', [MenuApiController::class, 'destroy'])->name('admin.api.menus.destroy');
        });
    });

    // Ayarlar
    Router::group(['middleware' => ['can:settings.manage']], function () {
        Router::get('/settings', [SettingController::class, 'index'])->name('admin.settings');
        Router::post('/settings/mail/test', [SettingController::class, 'mailTest'])->name('admin.settings.mailTest');
        Router::post('/settings/cache/clear', [SettingController::class, 'cacheClear'])->name('admin.settings.cacheClear');
        Router::post('/settings/{group}', [SettingController::class, 'update'])->name('admin.settings.update');
    });

    // SEO: yönlendirmeler, bulunamayan adresler
    Router::group(['prefix' => '/seo', 'middleware' => ['can:seo.manage']], function () {
        Router::get('/', [SeoController::class, 'redirects'])->name('admin.seo');
        Router::post('/', [SeoController::class, 'store'])->name('admin.seo.store');
        Router::post('/{id}', [SeoController::class, 'update'])->name('admin.seo.update');
        Router::post('/{id}/delete', [SeoController::class, 'destroy'])->name('admin.seo.delete');
        Router::get('/not-found', [SeoController::class, 'notFound'])->name('admin.seo.notfound');
        Router::post('/not-found/clear', [SeoController::class, 'clear'])->name('admin.seo.clear');
        Router::post('/not-found/{id}/forget', [SeoController::class, 'forget'])->name('admin.seo.forget');
    });

    // Modül koleksiyonları (SSS, ekip, referanslar, portfolyo...): yetkiyi koleksiyon tanımına göre controller denetler
    Router::get('/collections/{name}', [CollectionController::class, 'index'])->name('admin.collections');
    Router::get('/collections/{name}/create', [CollectionController::class, 'create'])->name('admin.collections.create');
    Router::post('/collections/{name}', [CollectionController::class, 'store'])->name('admin.collections.store');
    Router::post('/collections/{name}/sort', [CollectionController::class, 'sort'])->name('admin.collections.sort');
    Router::get('/collections/{name}/{id}/edit', [CollectionController::class, 'edit'])->name('admin.collections.edit');
    Router::post('/collections/{name}/{id}', [CollectionController::class, 'update'])->name('admin.collections.update');
    Router::post('/collections/{name}/{id}/toggle', [CollectionController::class, 'toggle'])->name('admin.collections.toggle');
    Router::post('/collections/{name}/{id}/delete', [CollectionController::class, 'destroy'])->name('admin.collections.delete');

    Router::group(['middleware' => ['can:backups.manage']], function () {
        Router::get('/backups', [BackupController::class, 'index'])->name('admin.backups');
        Router::post('/backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Router::get('/backups/{name}/download', [BackupController::class, 'download'])->name('admin.backups.download');
        Router::post('/backups/{name}/delete', [BackupController::class, 'destroy'])->name('admin.backups.delete');
    });

    Router::group(['middleware' => ['can:settings.manage']], function () {
        Router::get('/updates', [UpdateController::class, 'index'])->name('admin.updates');
        Router::post('/updates/apply', [UpdateController::class, 'apply'])->name('admin.updates.apply');
        Router::post('/updates/token', [UpdateController::class, 'token'])->name('admin.updates.token');
        Router::get('/languages', [LanguageController::class, 'index'])->name('admin.languages');
        Router::post('/languages', [LanguageController::class, 'store'])->name('admin.languages.store');
        Router::get('/languages/strings', [LanguageController::class, 'strings'])->name('admin.languages.strings');
        Router::post('/languages/strings', [LanguageController::class, 'saveStrings']);
        Router::post('/languages/{code}/default', [LanguageController::class, 'makeDefault'])->name('admin.languages.default');
        Router::post('/languages/{code}/delete', [LanguageController::class, 'destroy'])->name('admin.languages.delete');
    });

    Router::get('/activity', [ActivityController::class, 'index'])->middleware('can:activity.view')->name('admin.activity');

    // Kullanıcılar ve roller
    Router::group(['middleware' => ['can:users.manage']], function () {
        Router::get('/users', [UserController::class, 'index'])->name('admin.users.index');
        Router::get('/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Router::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Router::get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Router::post('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Router::post('/users/{id}/invite', [UserController::class, 'invite'])->name('admin.users.invite');
        Router::post('/users/{id}/two-factor-reset', [UserController::class, 'resetTwoFactor'])->name('admin.users.2fa.reset');
        Router::post('/users/{id}/delete', [UserController::class, 'destroy'])->name('admin.users.delete');

        Router::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        Router::get('/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
        Router::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Router::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
        Router::post('/roles/{id}', [RoleController::class, 'update'])->name('admin.roles.update');
        Router::post('/roles/{id}/delete', [RoleController::class, 'destroy'])->name('admin.roles.delete');
    });
});
