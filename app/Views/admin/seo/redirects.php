<?php // Admin > SEO > Yönlendirmeler. Değişkenler: $redirects (sayfalı), $q, $source (404'ten gelen), $notFound ?>
<?= View::render('admin/seo/tabs', ['current' => 'redirects']) ?>

<form method="post" action="<?= Router::url('admin.seo.store') ?>" class="card mb-6 grid gap-4 p-5 md:grid-cols-[1fr_1fr_9rem_auto] md:items-end">
  <?= View::csrf() ?>
  <?= View::render('partials/field', ['name' => 'source', 'label' => 'Eski adres', 'value' => $source ?: null, 'required' => true, 'placeholder' => 'eski-sayfa ya da eski-klasor/*']) ?>
  <?= View::render('partials/field', ['name' => 'target', 'label' => 'Yeni adres', 'required' => true, 'placeholder' => 'yeni-sayfa, yeni-klasor/* ya da https://...', 'attrs' => $source ? 'autofocus' : '']) ?>
  <?= View::render('partials/field', ['name' => 'code', 'label' => 'Tür', 'type' => 'select', 'options' => App\Models\Redirect::CODES, 'value' => 301]) ?>
  <button class="btn btn-primary md:mb-0.5">Ekle</button>
</form>
<p class="-mt-3 mb-6 text-xs text-muted-foreground">
  Sadece hiçbir sayfanın karşılamadığı adresler yönlenir. Sonu <code>*</code> olan adres altındaki her şeyi yönlendirir (kalan kısım korunur).
  Sayfa ya da yazı adresi değişince eski adres kendiliğinden eklenir ("otomatik").
  <?php if ($notFound): ?><a href="<?= Request::url('admin/seo/not-found') ?>" class="underline underline-offset-4"><?= (int) $notFound ?> bulunamayan adres</a> var.<?php endif ?>
</p>

<form class="mb-3"><input type="search" name="q" value="<?= View::e($q) ?>" placeholder="Adreslerde ara…" class="input mt-0 max-w-xs"></form>

<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Eski adres</th><th>Yeni adres</th><th>Tür</th><th class="hidden sm:table-cell">Kullanım</th><th class="text-right">İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($redirects['data'] as $r): ?>
        <tr>
          <td class="font-mono text-xs">/<?= View::e($r['source']) ?><?= $r['is_auto'] ? ' <span class="badge badge-gray ml-1 font-sans">otomatik</span>' : '' ?></td>
          <td class="font-mono text-xs"><?= preg_match('#^https?://#', $r['target']) ? View::e($r['target']) : '/' . View::e(ltrim($r['target'], '/')) ?></td>
          <td><span class="badge"><?= (int) $r['code'] ?></span></td>
          <td class="hidden text-muted-foreground sm:table-cell"><?= (int) $r['hits'] ?><?= $r['last_hit_at'] ? ' · ' . View::date($r['last_hit_at']) : '' ?></td>
          <td class="text-right">
            <form method="post" action="<?= Router::url('admin.seo.delete', ['id' => $r['id']]) ?>" data-confirm="Yönlendirme silinsin mi?">
              <?= View::csrf() ?><button class="btn btn-danger-ghost btn-sm">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$redirects['data']): ?><tr><td colspan="5" class="py-10 text-center text-muted-foreground">Yönlendirme yok.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>
<div class="mt-4"><?= View::render('partials/pagination', ['page' => $redirects]) ?></div>
