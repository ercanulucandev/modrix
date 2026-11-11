<?php
// Form alanı: echo View::render('partials/field', ['name' => 'title', 'label' => 'Başlık', 'value' => $post['title'] ?? null, 'required' => true])
// type: text, email, password, number, date, tel, url, textarea, select (options: [deger => etiket])
// Diğer: placeholder, help, rows, attrs (ek HTML özellikleri). Hata ve eski değer otomatik gelir.
$type   = $type ?? 'text';
$id     = 'f_' . preg_replace('/\W/', '_', $name);
$error  = View::error($name);
$value  = $type === 'password' ? '' : View::old($name, $value ?? null);
$common = 'id="' . $id . '" name="' . View::e($name) . '" class="input' . ($error ? ' input-error' : '') . '"'
        . ($error ? ' aria-invalid="true"' : '')
        . (!empty($required) ? ' required' : '') . (!empty($placeholder) ? ' placeholder="' . View::e($placeholder) . '"' : '') . ' ' . ($attrs ?? '');
?>
<div>
  <label for="<?= $id ?>" class="label"><?= View::e($label) ?><?= !empty($required) ? ' <span class="text-destructive">*</span>' : '' ?></label>
  <?php if ($type === 'textarea'): ?>
    <textarea <?= $common ?> rows="<?= (int) ($rows ?? 5) ?>"><?= View::e($value) ?></textarea>
  <?php elseif ($type === 'select'): ?>
    <select <?= $common ?>>
      <?php foreach ($options as $k => $v): ?><option value="<?= View::e($k) ?>" <?= (string) $k === (string) $value ? 'selected' : '' ?>><?= View::e($v) ?></option><?php endforeach ?>
    </select>
  <?php else: ?>
    <input type="<?= View::e($type) ?>" value="<?= View::e($value) ?>" <?= $common ?>>
  <?php endif ?>
  <?php if ($error): ?><p class="field-error"><?= View::e($error) ?></p>
  <?php elseif (!empty($help)): ?><p class="field-help"><?= View::e($help) ?></p><?php endif ?>
</div>
