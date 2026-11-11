/**
 * Sayfalama çubuğu (API'nin meta'sından).
 *
 *   renderPagination(container, meta, (page) => load(page));
 *   meta: { total, page, pages, from, to }   (DB::paginate / ->paginate() çıktısı)
 *
 * Tek sayfa varsa container boşaltılır. Görünüm: "135 kayıttan 21–40 arası   ‹ 1 … 4 5 [6] 7 8 … 20 ›"
 */
import { h, icon } from './dom.js';

export function renderPagination(container, meta, onPage, { compact = false } = {}) {
  if (!meta || meta.pages <= 1) {
    container.replaceChildren();
    return;
  }

  const button = (label, page, { disabled = false, active = false, aria } = {}) => h('button', {
    type: 'button',
    class: `btn btn-sm min-w-8 px-2.5 ${active ? 'btn-secondary' : 'btn-ghost'}`,
    disabled: disabled || active,
    'aria-current': active ? 'page' : null,
    'aria-label': aria,
    onClick: () => onPage(page),
  }, label);

  const near = compact ? 1 : 2;
  const pages = [...new Set([1, meta.pages, ...range(meta.page - near, meta.page + near)])]
    .filter((n) => n >= 1 && n <= meta.pages)
    .sort((a, b) => a - b);

  const items = [];
  pages.forEach((n, i) => {
    if (i && n - pages[i - 1] > 1) items.push(h('span', { class: 'px-1 text-muted-foreground' }, '…'));
    items.push(button(String(n), n, { active: n === meta.page }));
  });

  container.replaceChildren(
    h('p', { class: 'text-sm text-muted-foreground' }, `${meta.total} kayıttan ${meta.from}–${meta.to} arası`),
    h('div', { class: 'flex items-center gap-1' }, [
      button(icon('left'), meta.page - 1, { disabled: meta.page <= 1, aria: 'Önceki sayfa' }),
      ...items,
      button(icon('right'), meta.page + 1, { disabled: meta.page >= meta.pages, aria: 'Sonraki sayfa' }),
    ]),
  );
}

function range(from, to) {
  return Array.from({ length: to - from + 1 }, (_, i) => from + i);
}
