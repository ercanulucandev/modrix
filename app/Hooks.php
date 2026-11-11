<?php
// Uygulama genelindeki hook kayıtları ve açılış ayarları.
// Her istekte route'lardan önce yüklenir.
//
//   Hook::on('olay.adi', function (...$args) { ... });     dinle
//   Hook::fire('olay.adi', $veri);                          tetikle (ör. modelde kayıt sonrası)
//   Hook::filter('olay.adi', $deger);                       değeri dinleyicilerden geçir
//
// Sistem olayları: app.boot, request.before ($method, $path), response.after ($html -> filter)

// Hata sayfası (debug kapalıyken kullanıcıya gösterilen)
Core\ErrorHandler::setView('errors/error', fn() => str_starts_with(Request::path(), '/admin')
    ? (App\Models\User::admin() ? 'admin' : 'admin-auth')   // panelde panel görünümüyle
    : 'site');

// Sayfa/yazı adresi değişince eski adresten yenisine otomatik 301 (Admin > SEO > Yönlendirmeler)
//   Hook::fire('url.moved', 'eski/adres', 'yeni/adres');
Hook::on('url.moved', fn(string $from, string $to) => App\Models\Redirect::moved($from, $to));

// Sayfa önbelleği (Ayarlar > Performans): panelde her kayıttan sonra temizlenir; ziyaretçi sayfaları hazır kopyadan
use App\Services\PageCache;
Hook::on('request.before', function (string $method, string $path) {
    if ($method !== 'GET' && $method !== 'HEAD' && str_starts_with($path, '/admin')) register_shutdown_function([PageCache::class, 'flush']);
    PageCache::serve($method, $path);
});
Hook::on('response.after', fn(string $html) => PageCache::store($html), 100);

// İçerikteki medya görsellerine srcset (telefona küçük kopya), boyut ve lazy yükleme
Hook::on('content.html', fn(string $html) => App\Services\ResponsiveImages::apply($html), 90);

// Çoklu dil: diğer dillerin ana sayfaları site haritasında
Hook::on('sitemap.urls', fn(array $urls) => [...$urls, ...array_map(fn($code) => ['loc' => $code, 'priority' => '1.0'], array_slice(array_keys(Lang::all()), 1))]);

// Çoklu dil: üst kısımda dil seçici (birden çok dil varsa)
Hook::on('site.header_actions', fn(array $html) => Lang::enabled() ? [...$html, (string) View::render('partials/language-switcher')] : $html, 5);

// Genel bakış: Modrix'in yeni sürümü (12 saatte bir denetlenir)
Hook::on('admin.dashboard.todos', function (array $t) {
    $new = App\Services\Updater::available();
    return $new ? [...$t, ['text' => "Modrix v{$new['version']} yayınlandı (kullandığınız: v" . App\Services\Updater::current() . ').', 'url' => 'admin/updates', 'tone' => 'info', 'order' => 5, 'permission' => 'settings.manage']] : $t;
});

// İşlem kaydı (Admin > Kullanıcılar > İşlem kaydı): içerik, medya, modül ve tema olayları
use App\Services\Activity;
Hook::on('post.created', fn(array $p) => Activity::log('post.created', "\"{$p['title']}\" yazısını oluşturdu", "admin/blog/{$p['id']}/edit"));
Hook::on('post.updated', fn(array $p) => Activity::log('post.updated', "\"{$p['title']}\" yazısını güncelledi", "admin/blog/{$p['id']}/edit"));
Hook::on('post.deleted', fn(array $p) => Activity::log('post.deleted', "\"{$p['title']}\" yazısını sildi"));
Hook::on('page.created', fn(array $p) => Activity::log('page.created', "\"{$p['title']}\" sayfasını oluşturdu", "admin/pages/{$p['id']}/edit"));
Hook::on('page.updated', fn(array $p) => Activity::log('page.updated', "\"{$p['title']}\" sayfasını güncelledi", "admin/pages/{$p['id']}/edit"));
Hook::on('page.deleted', fn(array $p) => Activity::log('page.deleted', "\"{$p['title']}\" sayfasını sildi"));
Hook::on('media.uploaded', fn(array $m) => Activity::log('media.uploaded', "Dosya yükledi: {$m['name']}", 'admin/media'));
Hook::on('media.replaced', fn(array $m) => Activity::log('media.replaced', "Görseli düzenleyip değiştirdi: {$m['name']}", 'admin/media'));
Hook::on('media.deleted', fn(array $m) => Activity::log('media.deleted', "Dosya sildi: {$m['name']}"));
Hook::on('theme.activated', fn(string $f) => Activity::log('appearance.theme', "Temayı etkinleştirdi: $f", 'admin/appearance'));
Hook::on('module.installed', fn(string $f) => Activity::log('module.installed', "Modülü kurdu: $f", 'admin/modules'));
Hook::on('module.updated', fn(string $f, $from = null, $to = null) => Activity::log('module.updated', "Modülü güncelledi: $f" . ($to ? " ($from → $to)" : ''), 'admin/modules'));
Hook::on('module.uninstalled', fn(string $f) => Activity::log('module.uninstalled', "Modülü kaldırdı: $f", 'admin/modules'));

// Modüller kendi hook'larını app/Modules/{Ad}/hooks.php içinde kaydeder (sadece aktifken yüklenir).
//
// Kullanılabilir noktalar:
//   site.menu                 site menüsü öğeleri (module.json -> menu.site ile de eklenir)
//   admin.menu                admin menüsü öğeleri (module.json -> menu.admin)
//   site.home                 ana sayfa bölümleri: ['key' => 'blog.latest', 'order' => 10, 'html' => '...'] (key isteğe bağlı; temalar tanır)
//   admin.dashboard.stats     genel bakış kutuları: ['label', 'value', 'url', 'order']
//   admin.dashboard.widgets   genel bakış bölümleri: ['order', 'html']
//   admin.settings            genel Ayarlar sayfasına sekme (core için; modüller settings.php kullanır)
//   site.head                 sitenin <head> kısmına HTML: [...$h, '<link ...>']
//   site.body_end             sitenin sayfa sonuna HTML (sabit düğmeler, bildirimler): [...$h, '<div ...>']
//   site.front_page           ana sayfa yerine gösterilecek View (null: temanın ana sayfası)
//   page.created / page.updated / page.deleted   (Sayfalar modülü)
//   content.html              sayfa/yazı içeriği yazdırılmadan önce (kısa kodlar: [form iletisim])
//   form.submitted            form gönderildi ($form, $data)
//   menu.links                Görünüm > Menüler'deki hazır bağlantılar: ['group', 'label', 'url']
//   theme.activated           tema etkinleştirildi ($folder)
//   media.uploaded / media.replaced / media.deleted
//   module.installed / module.updated / module.uninstalled
