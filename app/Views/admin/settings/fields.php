<?php
// Ayar alanları (formun içi): Ayarlar, modül ayarları ve Görünüm > Özelleştir ortak kullanır.
// Değişkenler: $fields (['anahtar' => ['label', 'type', 'rules', 'default', 'help', 'options'...]]), $values (kayıtlı değerler)
// Görsel alanları için sayfada app/Assets/admin/settings-page.js yüklenmeli.
$val = fn(string $key, array $f) => View::old($key, $values[$key] ?? ($f['default'] ?? null));
?>
<?php foreach ($fields as $key => $f): $type = $f['type']; $error = View::error($key); $value = $val($key, $f); ?>
<div data-field="<?= View::e($key) ?>">
  <?php $translations = !empty($translate) && App\Services\SettingForm::translatable($f) ? array_slice(array_keys(Lang::all()), 1) : []; ?>

  <?php if ($type === 'checkbox'): ?>
    <label class="flex cursor-pointer items-start justify-between gap-6 rounded-md border p-4">
      <span>
        <span class="label"><?= View::e($f['label']) ?></span>
        <?php if (!empty($f['help'])): ?><span class="mt-1.5 block text-sm text-muted-foreground"><?= View::e($f['help']) ?></span><?php endif ?>
      </span>
      <input type="checkbox" name="<?= $key ?>" value="1" class="peer sr-only" <?= (string) ($values[$key] ?? $f['default'] ?? '0') === '1' ? 'checked' : '' ?>>
      <span class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full bg-input transition-colors peer-checked:bg-primary
                   after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-background after:shadow-sm after:transition-transform peer-checked:after:translate-x-4"></span>
    </label>

  <?php elseif ($type === 'image'): $path = (string) ($values[$key] ?? ''); $url = $path !== '' ? Request::url('media/' . $path) : null; ?>
    <?php // Medya kütüphanesinden seçilir (app/Assets/admin/settings-page.js); değer: dosya yolu ?>
    <div data-media-field>
      <span class="label"><?= View::e($f['label']) ?></span>
      <input type="hidden" name="<?= $key ?>" value="<?= View::e($path) ?>" data-media-value>
      <div class="mt-2 flex flex-wrap items-center gap-4 rounded-md border p-4 <?= $error ? 'border-destructive' : '' ?>">
        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted" data-media-preview>
          <?php if ($url): ?><img src="<?= View::e($url) ?>" alt="" class="max-h-full max-w-full object-contain">
          <?php else: ?><span class="text-xs text-muted-foreground">Yok</span><?php endif ?>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm" data-media-name><?= $path ? View::e(basename($path)) : '<span class="text-muted-foreground">Görsel seçilmedi</span>' ?></p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" class="btn btn-secondary btn-sm" data-media-choose>Kütüphaneden seç</button>
            <button type="button" class="btn btn-ghost btn-sm text-destructive <?= $path ? '' : 'hidden' ?>" data-media-clear>Kaldır</button>
          </div>
        </div>
      </div>
      <?php if ($error): ?><p class="field-error"><?= View::e($error) ?></p>
      <?php elseif (!empty($f['help'])): ?><p class="field-help"><?= View::e($f['help']) ?></p><?php endif ?>
    </div>

  <?php elseif ($type === 'gallery'): $paths = array_values(array_filter((array) ($values[$key] ?? []), 'is_string')); ?>
    <?php // Birden çok görsel (koleksiyonlar): değer JSON dizi; ekle, sürükleyerek sırala, kaldır (settings-page.js) ?>
    <div data-gallery-field>
      <span class="label"><?= View::e($f['label']) ?></span>
      <input type="hidden" name="<?= $key ?>" value="<?= View::e(json_encode($paths, JSON_UNESCAPED_SLASHES)) ?>" data-gallery-value>
      <div class="mt-2 rounded-md border p-3">
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5" data-gallery-list>
          <?php foreach ($paths as $p): ?>
            <div class="group relative aspect-square overflow-hidden rounded-md border bg-muted" data-path="<?= View::e($p) ?>">
              <img src="<?= View::e(Request::url('media/' . $p)) ?>" alt="" class="size-full cursor-grab object-cover">
              <button type="button" class="absolute right-1 top-1 rounded bg-black/60 px-1.5 text-xs text-white" data-gallery-remove aria-label="Kaldır">✕</button>
            </div>
          <?php endforeach ?>
        </div>
        <button type="button" class="btn btn-secondary btn-sm mt-3" data-gallery-add>Görsel ekle</button>
        <span class="ml-2 text-xs text-muted-foreground">Sürükleyerek sıralayın.</span>
      </div>
      <?php if (!empty($f['help'])): ?><p class="field-help"><?= View::e($f['help']) ?></p><?php endif ?>
    </div>

  <?php elseif ($type === 'color'): ?>
    <div>
      <label for="f_<?= $key ?>" class="label"><?= View::e($f['label']) ?></label>
      <div class="mt-2 flex items-center gap-2">
        <input type="color" value="<?= View::e($value) ?>" class="h-9 w-12 cursor-pointer rounded-md border border-input bg-background p-1"
               oninput="this.nextElementSibling.value = this.value">
        <input id="f_<?= $key ?>" name="<?= $key ?>" value="<?= View::e($value) ?>" class="input mt-0 w-32 font-mono <?= $error ? 'input-error' : '' ?>"
               oninput="if (/^#[0-9a-f]{6}$/i.test(this.value)) this.previousElementSibling.value = this.value">
      </div>
      <?php if ($error): ?><p class="field-error"><?= View::e($error) ?></p>
      <?php elseif (!empty($f['help'])): ?><p class="field-help"><?= View::e($f['help']) ?></p><?php endif ?>
    </div>

  <?php elseif ($type === 'code'): ?>
    <div>
      <label for="f_<?= $key ?>" class="label"><?= View::e($f['label']) ?></label>
      <textarea id="f_<?= $key ?>" name="<?= $key ?>" rows="6" spellcheck="false"
                class="input font-mono text-xs <?= $error ? 'input-error' : '' ?>"><?= View::e($value) ?></textarea>
      <?php if ($error): ?><p class="field-error"><?= View::e($error) ?></p><?php endif ?>
    </div>

  <?php else: ?>
    <?= View::render('partials/field', [
        'name'        => $key,
        'label'       => $f['label'],
        'type'        => $type,
        'value'       => $values[$key] ?? ($f['default'] ?? null),
        'options'     => $f['options'] ?? [],
        'help'        => $f['help'] ?? null,
        'placeholder' => $f['placeholder'] ?? null,
        'required'    => str_contains($f['rules'] ?? '', 'required'),
        'rows'        => 3,
    ]) ?>
  <?php endif ?>

  <?php if ($translations): // diğer dillerdeki karşılığı (boşsa varsayılan dildeki görünür) ?>
    <div class="mt-2 space-y-2 border-l-2 pl-3">
      <?php foreach ($translations as $code): $tkey = Setting::translationKey($key, $code); $tval = View::old($tkey, Setting::raw()[$tkey] ?? ''); ?>
        <label class="flex items-start gap-2 text-sm">
          <span class="mt-2 w-8 shrink-0 font-mono text-xs uppercase text-muted-foreground" title="<?= View::e(Lang::name($code)) ?>"><?= $code ?></span>
          <?php if ($type === 'textarea'): ?>
            <textarea name="<?= $tkey ?>" rows="2" class="input mt-0" placeholder="<?= View::e(Lang::name($code)) ?>" dir="<?= Lang::rtl($code) ? 'rtl' : 'ltr' ?>"><?= View::e((string) $tval) ?></textarea>
          <?php else: ?>
            <input name="<?= $tkey ?>" value="<?= View::e((string) $tval) ?>" class="input mt-0" placeholder="<?= View::e(Lang::name($code)) ?>" dir="<?= Lang::rtl($code) ? 'rtl' : 'ltr' ?>">
          <?php endif ?>
        </label>
        <?php if ($e = View::error($tkey)): ?><p class="field-error"><?= View::e($e) ?></p><?php endif ?>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
<?php endforeach ?>
