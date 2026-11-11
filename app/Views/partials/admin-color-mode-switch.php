<?php // Renk modu seçici: Açık / Koyu / Sistem ?>
<div class="flex items-center rounded-md border p-0.5" role="radiogroup" aria-label="Renk modu" data-color-mode>
  <?php foreach (['light' => ['Açık', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>'],
                   'dark'  => ['Koyu', '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'],
                   'system' => ['Sistem', '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>']] as $mode => [$label, $path]): ?>
    <button type="button" role="radio" data-mode="<?= $mode ?>" title="<?= $label ?>" aria-label="<?= $label ?>"
            class="flex size-7 items-center justify-center rounded text-muted-foreground transition-colors hover:text-foreground aria-checked:bg-accent aria-checked:text-foreground">
      <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $path ?></svg>
    </button>
  <?php endforeach ?>
</div>
<script>
  (() => {
    const box = document.currentScript.previousElementSibling;
    const media = matchMedia('(prefers-color-scheme: dark)');
    const apply = (pref) => {
      document.documentElement.classList.toggle('dark', pref === 'dark' || (pref === 'system' && media.matches));
      document.documentElement.dataset.themePref = pref;
      box.querySelectorAll('[data-mode]').forEach((b) => b.setAttribute('aria-checked', b.dataset.mode === pref));
    };
    box.addEventListener('click', (e) => {
      const b = e.target.closest('[data-mode]'); if (!b) return;
      try { localStorage.setItem('modrix-admin-theme', b.dataset.mode); } catch {}
      apply(b.dataset.mode);
    });
    // Sistem modunda işletim sisteminin teması değişince anında uyar
    media.addEventListener('change', () => { if (document.documentElement.dataset.themePref === 'system') apply('system'); });
    apply(document.documentElement.dataset.themePref || 'system');
  })();
</script>
