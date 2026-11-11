<?php // Admin > SEO > Bulunamayanlar. Değişken: $rows (sayfalı) ?>
<?= View::render('admin/seo/tabs', ['current' => 'notfound']) ?>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-muted-foreground">Ziyaretçilerin ulaşamadığı adresler (en çok istenen üstte). Kırık bir bağlantıysa doğru sayfaya yönlendirin.</p>
  <?php if ($rows['data']): ?>
    <form method="post" action="<?= Router::url('admin.seo.clear') ?>" data-confirm="Tüm kayıtlar silinsin mi?"><?= View::csrf() ?><button class="btn btn-secondary btn-sm">Hepsini temizle</button></form>
  <?php endif ?>
</div>

<div class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>Adres</th><th>İstek</th><th class="hidden md:table-cell">Son gelinen yer</th><th class="hidden sm:table-cell">Son</th><th class="text-right">İşlem</th></tr></thead>
    <tbody>
      <?php foreach ($rows['data'] as $r): ?>
        <tr>
          <td class="max-w-xs truncate font-mono text-xs">/<?= View::e($r['path']) ?></td>
          <td class="tabular-nums"><?= (int) $r['hits'] ?></td>
          <td class="hidden max-w-xs truncate text-xs text-muted-foreground md:table-cell"><?= $r['referer'] ? View::e($r['referer']) : '—' ?></td>
          <td class="hidden whitespace-nowrap text-muted-foreground sm:table-cell"><?= View::date($r['last_seen']) ?></td>
          <td>
            <div class="flex justify-end gap-2">
              <a href="<?= Request::url('admin/seo?source=' . rawurlencode($r['path'])) ?>" class="btn btn-secondary btn-sm">Yönlendir</a>
              <form method="post" action="<?= Router::url('admin.seo.forget', ['id' => $r['id']]) ?>"><?= View::csrf() ?><button class="btn btn-ghost btn-sm">Yoksay</button></form>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (!$rows['data']): ?><tr><td colspan="5" class="py-10 text-center text-muted-foreground">Kayıt yok. Harika!</td></tr><?php endif ?>
    </tbody>
  </table>
</div>
<div class="mt-4"><?= View::render('partials/pagination', ['page' => $rows]) ?></div>
