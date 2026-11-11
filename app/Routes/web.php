<?php
// Site route'ları. Modüllerin route'ları app/Modules/*/routes.php içindedir.
// Bu dosyadaki route'lar 'web' middleware grubundadır (CSRF koruması).
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\ModuleAssetController;
use App\Controllers\SearchController;
use App\Controllers\SeoController;
use App\Controllers\ThemeAssetController;

Router::get('/', [HomeController::class, 'index'])->name('home');
Router::get('/search', [SearchController::class, 'index'])->name('search');

// Ayarlardan yüklenen logo, favicon vb.
Router::get('/media/{path*}', [MediaController::class, 'show'])->name('media');

// Modüllerin JS/CSS/görselleri: /assets/blog/admin/posts-page.js -> app/Modules/Blog/Assets/admin/posts-page.js
// Adres için: Module::asset('Blog', 'admin/posts-page.js')
Router::get('/assets/{module}/{path*}', [ModuleAssetController::class, 'show'])->name('module.asset');

// Temaların dosyaları: /theme/kurumsal/css/style.css -> app/Themes/Kurumsal/assets/css/style.css
// Adres için: Theme::asset('css/style.css')
Router::get('/theme/{theme}/{path*}', [ThemeAssetController::class, 'show'])->name('theme.asset');

// Arama motorları
Router::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Router::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Yönlendirmeler (Admin > SEO): hiçbir rota eşleşmezse önce bunlara bakılır (modüllerin fallback'lerinden, ör. Sayfalar, önce)
Router::fallback(fn(string $path) => SeoController::followRedirect($path));

// Bulunamayan sayfalar: önce yönlendirme var mı, yoksa 404 (Admin > SEO > Bulunamayanlar'a kaydedilir)
Router::notFound(function () {
    // Rota eşleşip içerik bulunamadıysa da (ör. /blog/eski-adres) yönlendirmeye bak
    if (Request::method() === 'GET') SeoController::followRedirect(Request::path());
    SeoController::logNotFound();
    // Panelde (ör. silinmiş kayıt) panel düzeniyle, sitede temanın düzeniyle
    $layout = !str_starts_with(Request::path(), '/admin') ? 'site' : (App\Models\User::admin() ? 'admin' : 'admin-auth');
    return View::render('errors/404', ['title' => 'Sayfa bulunamadı'])->setLayout($layout);
});
