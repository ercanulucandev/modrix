<?php
// Theme::footer() çıktısı: temaların </body> öncesine koyduğu ortak kısım (wp_footer gibi).
// Onay soran formlar (data-confirm), Ayarlar > Özel kod, modüllerin 'site.body_end' eklentileri, tema önizleme çubuğu.
// Değişkenler: $preview (önizlenen tema klasörü ya da null), $customize (Özelleştir önizlemesinde bölüm adları, değilse null)
?>
  <script>
    document.addEventListener('submit', (e) => {
      const msg = e.target.dataset.confirm;
      if (msg && !confirm(msg)) e.preventDefault();
    });
  </script>
  <?= Setting::get('footer_code') ?>
  <?php // Modüllerin sayfa sonuna eklediği HTML (ör. WhatsApp düğmesi): Hook::on('site.body_end', fn(array $h) => [...$h, '...']) ?>
  <?= implode("\n", Hook::filter('site.body_end', [])) ?>

  <?php if ($preview): $theme = Theme::manifest($preview); ?>
    <?php // Tema önizleme çubuğu: sadece önizleme yapan admin görür; Özelleştir sayfasının iframe'inde gizlenir ?>
    <div id="theme-preview-bar" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483000;display:flex;align-items:center;gap:12px;padding:8px 8px 8px 16px;border-radius:999px;background:#09090b;color:#fafafa;font:500 13px/1.2 ui-sans-serif,system-ui,sans-serif;box-shadow:0 10px 30px rgb(0 0 0/.3);white-space:nowrap">
      <span>Önizleme: <strong><?= View::e($theme['name']) ?></strong></span>
      <form method="post" action="<?= Router::url('admin.appearance.activate', ['name' => $preview]) ?>" style="margin:0">
        <?= View::csrf() ?><button style="all:unset;cursor:pointer;padding:6px 12px;border-radius:999px;background:#fafafa;color:#09090b">Etkinleştir</button>
      </form>
      <form method="post" action="<?= Router::url('admin.appearance.preview.stop') ?>" style="margin:0">
        <?= View::csrf() ?><button style="all:unset;cursor:pointer;padding:6px 12px;border-radius:999px;color:#a1a1aa">Kapat</button>
      </form>
    </div>
    <script>if (window.self !== window.top) document.getElementById('theme-preview-bar').remove();</script>
  <?php endif ?>

  <?php if ($customize): ?>
    <?php // Özelleştir önizlemesi: Theme::editable() ile işaretli bölümlerin üzerine gelince "Düzenle" düğmesi, alanlara tıklayınca paneldeki alan açılır ?>
    <div id="modrix-edit" hidden>
      <div class="modrix-edit-section"></div>
      <div class="modrix-edit-field"></div>
      <button type="button" class="modrix-edit-button"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.17 6.81a1 1 0 0 0-3.98-3.98L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/></svg><span></span></button>
    </div>
    <style>
      #modrix-edit .modrix-edit-section, #modrix-edit .modrix-edit-field { position: fixed; z-index: 2147482000; pointer-events: none; border-radius: 6px; display: none; }
      #modrix-edit .modrix-edit-section { outline: 2px solid #3b82f6; outline-offset: -2px; box-shadow: inset 0 0 0 9999px rgb(59 130 246 / .04); }
      #modrix-edit .modrix-edit-field { outline: 2px dashed #3b82f6; outline-offset: 3px; }
      #modrix-edit .modrix-edit-button { position: fixed; z-index: 2147482001; display: none; align-items: center; gap: 6px; padding: 6px 10px; border: 0; border-radius: 999px;
        background: #3b82f6; color: #fff; font: 600 12px/1 ui-sans-serif, system-ui, sans-serif; cursor: pointer; box-shadow: 0 6px 20px rgb(0 0 0 / .25); }
      #modrix-edit .modrix-edit-button:hover { background: #2563eb; }
      [data-customize-field] { cursor: pointer; }
    </style>
    <script>
      (() => {
        const labels = <?= json_encode($customize, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        const root = document.getElementById('modrix-edit');
        const sectionBox = root.querySelector('.modrix-edit-section');
        const fieldBox = root.querySelector('.modrix-edit-field');
        const button = root.querySelector('.modrix-edit-button');
        root.hidden = false;
        let section = null, field = null;

        const send = (sectionKey, fieldKey = null) =>
          window.parent.postMessage({ type: 'modrix:customize', section: sectionKey, field: fieldKey }, window.location.origin);

        const place = () => {
          if (!section || !document.contains(section)) { sectionBox.style.display = button.style.display = 'none'; section = null; }
          else {
            const r = section.getBoundingClientRect();
            Object.assign(sectionBox.style, { display: 'block', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' });
            Object.assign(button.style, { display: 'flex', left: Math.max(8, r.left + 8) + 'px', top: Math.min(Math.max(8, r.top + 8), window.innerHeight - 40) + 'px' });
          }
          if (!field || !document.contains(field)) { fieldBox.style.display = 'none'; field = null; }
          else {
            const r = field.getBoundingClientRect();
            Object.assign(fieldBox.style, { display: 'block', left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' });
          }
        };

        document.addEventListener('mouseover', (e) => {
          if (button.contains(e.target)) return;
          const s = e.target.closest('[data-customize]');
          const f = e.target.closest('[data-customize-field]');
          if (s !== section) {
            section = s;
            const name = s ? (labels[s.dataset.customize] ?? 'Bölüm') : '';
            button.querySelector('span').textContent = name + ' · düzenle';
            button.dataset.section = s ? s.dataset.customize : '';
          }
          field = f;
          place();
        });
        document.addEventListener('mouseleave', () => { section = field = null; place(); });
        window.addEventListener('scroll', place, { passive: true });
        window.addEventListener('resize', place);

        button.addEventListener('click', (e) => { e.preventDefault(); send(button.dataset.section); });

        // Alana tıklama: bağlantıya gitme, paneldeki alanı aç
        document.addEventListener('click', (e) => {
          const f = e.target.closest('[data-customize-field]');
          if (!f) return;
          e.preventDefault();
          e.stopPropagation();
          send(f.dataset.customizeSection, f.dataset.customizeField);
        }, true);
      })();
    </script>
  <?php endif ?>
