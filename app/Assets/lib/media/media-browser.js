/**
 * Medya tarayıcısı: arama, tür filtresi, ızgara, sayfalama, sürükle-bırak ile yükleme.
 * Hem Admin > Medya sayfası hem de seçici modalı (openMediaPicker) bunu kullanır.
 *
 *   const browser = createMediaBrowser({
 *     accept: 'image',          // 'image' -> sadece görseller | 'all'
 *     perPage: 24,
 *     onSelect: (media) => {},  // tıklanınca (seçim değişince; null = seçim kalktı)
 *     onActivate: (media) => {},// çift tıklama / seçiliyken Enter (seçicide "bunu kullan")
 *
 *     // Çoklu seçim (Admin > Medya): kutucuktaki onay kutusu, Ctrl/Cmd+tık (ekle/çıkar), Shift+tık (aralık),
 *     // Ctrl+A (sayfadakilerin hepsi), Esc (seçimi kaldır). Seçim sayfalar arasında korunur.
 *     multiple: true,
 *     onSelectionChange: (items) => {},             // seçili kayıtlar (dizi)
 *     bulkActions: [{ label, icon, danger, run: async (items) => {} }],   // seçim çubuğundaki düğmeler
 *   });
 *   container.append(browser.element);
 *   browser.selected()     browser.selection()     browser.clearSelection()
 *   browser.reload()       browser.replace(media)  browser.removeItem(id)    browser.removeItems([id])
 */
import { h, icon, debounce } from '../dom.js';
import { toast } from '../toast.js';
import { renderPagination } from '../pagination.js';
import { mediaApi } from './media-api.js';

export function createMediaBrowser({ accept = 'all', perPage = 24, onSelect, onActivate, multiple = false, onSelectionChange, bulkActions = [] } = {}) {
  const imagesOnly = accept === 'image';
  const state = { q: '', type: imagesOnly ? 'image' : '', page: 1, items: [], selected: null, limits: null, selection: new Map(), anchor: null };

  // ---------------------------------------------------------------- iskelet

  const fileInput = h('input', {
    type: 'file', multiple: true, class: 'hidden',
    accept: imagesOnly ? 'image/png,image/jpeg,image/gif,image/webp,image/x-icon,.ico' : null,
    onChange: (e) => { upload([...e.target.files]); e.target.value = ''; },
  });

  const search = h('input', {
    type: 'search', class: 'input mt-0 pl-8', placeholder: 'Dosya adı, başlık ya da alt metinde ara…', 'aria-label': 'Ara', autofocus: true,
  });
  search.addEventListener('input', debounce(() => { state.q = search.value.trim(); state.page = 1; load(); }, 300));

  const typeFilter = imagesOnly ? null : h('select', {
    class: 'input mt-0 w-auto', 'aria-label': 'Tür',
    onChange: (e) => { state.type = e.target.value; state.page = 1; load(); },
  }, [
    h('option', { value: '' }, 'Tüm dosyalar'),
    h('option', { value: 'image' }, 'Görseller'),
    h('option', { value: 'document' }, 'Belgeler'),
  ]);

  const toolbar = h('div', { class: 'flex flex-wrap items-center gap-2' }, [
    h('div', { class: 'relative min-w-48 flex-1' }, [
      icon('search', 'pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground'),
      search,
    ]),
    typeFilter,
    h('button', { type: 'button', class: 'btn btn-primary', onClick: () => fileInput.click() }, [icon('upload'), 'Yükle']),
    fileInput,
  ]);

  const queue = h('div', { class: 'space-y-2 empty:hidden' });

  // Seçim çubuğu (çoklu seçimde, en az bir dosya seçiliyken)
  const bar = h('div', { class: 'hidden flex-wrap items-center gap-2 rounded-md border bg-muted/50 px-3 py-2 text-sm' });
  function renderBar() {
    const count = state.selection.size;
    bar.classList.toggle('hidden', !multiple || count === 0);
    bar.classList.toggle('flex', multiple && count > 0);
    if (!multiple || !count) return;
    const allOnPage = state.items.length > 0 && state.items.every((m) => state.selection.has(m.id));
    bar.replaceChildren(
      h('span', { class: 'flex items-center gap-2 font-medium' }, [
        h('span', { class: 'flex size-5 items-center justify-center rounded bg-primary text-primary-foreground' }, icon('check', 'size-3.5')),
        `${count} dosya seçili`,
      ]),
      h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onClick: () => (allOnPage ? removeFromSelection(state.items) : addToSelection(state.items)) },
        allOnPage ? 'Sayfadakileri bırak' : 'Sayfadakileri seç'),
      h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onClick: () => setSelection([]) }, 'Seçimi kaldır'),
      h('span', { class: 'flex-1' }),
      ...bulkActions.map((action) => h('button', {
        type: 'button',
        class: `btn btn-sm ${action.danger ? 'btn-danger-ghost' : 'btn-secondary'}`,
        onClick: async (e) => {
          const button = e.currentTarget;
          button.disabled = true;
          try { await action.run([...state.selection.values()]); } finally { button.disabled = false; }
        },
      }, [action.icon ? icon(action.icon, 'size-3.5') : null, action.label])),
    );
  }
  const grid = h('div', { class: 'grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5', role: 'listbox', 'aria-label': 'Dosyalar' });
  const pagination = h('div', { class: 'flex flex-col items-center justify-between gap-3 sm:flex-row' });

  const dropHint = h('div', {
    class: 'pointer-events-none absolute inset-0 z-10 hidden flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-primary bg-background/90 text-sm font-medium',
  }, [icon('upload', 'size-6'), 'Yüklemek için bırakın']);

  const element = h('div', { class: 'relative space-y-4' }, [toolbar, queue, bar, grid, pagination, dropHint]);

  // Klavye: Ctrl+A sayfadakileri seçer, Esc seçimi kaldırır (yazı alanındayken değil)
  if (multiple) element.addEventListener('keydown', (e) => {
    if (e.target.matches('input, textarea, select')) return;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') { e.preventDefault(); addToSelection(state.items); }
    if (e.key === 'Escape' && state.selection.size) { e.preventDefault(); setSelection([]); }
  });

  // Sürükle-bırak: tüm alana dosya bırakılabilir
  let dragDepth = 0;
  const hasFiles = (e) => [...(e.dataTransfer?.types ?? [])].includes('Files');
  element.addEventListener('dragenter', (e) => { if (!hasFiles(e)) return; e.preventDefault(); dragDepth++; toggleDrop(true); });
  element.addEventListener('dragover', (e) => { if (hasFiles(e)) e.preventDefault(); });
  element.addEventListener('dragleave', () => { if (--dragDepth <= 0) toggleDrop(false); });
  element.addEventListener('drop', (e) => {
    if (!hasFiles(e)) return;
    e.preventDefault();
    dragDepth = 0;
    toggleDrop(false);
    upload([...e.dataTransfer.files]);
  });
  function toggleDrop(on) {
    dropHint.classList.toggle('hidden', !on);
    dropHint.classList.toggle('flex', on);
  }

  // ---------------------------------------------------------------- liste

  async function load() {
    renderSkeleton();
    try {
      const { data, meta } = await mediaApi.list({ q: state.q, type: state.type, page: state.page, perPage });
      state.items = data;
      state.page = meta.page;
      state.limits = meta.limits;
      state.anchor = null;
      renderGrid();
      renderBar();
      renderPagination(pagination, meta, (page) => { state.page = page; load(); }, { compact: true });
    } catch (err) {
      grid.replaceChildren(h('p', { class: 'col-span-full py-12 text-center text-sm text-destructive' }, err.message));
      pagination.replaceChildren();
    }
  }

  function renderSkeleton() {
    grid.replaceChildren(...Array.from({ length: Math.min(perPage, 10) }, () =>
      h('div', { class: 'aspect-square animate-pulse rounded-md bg-muted' })));
  }

  function renderGrid() {
    if (!state.items.length) {
      const filtered = state.q || (state.type && !imagesOnly);
      grid.replaceChildren(h('div', { class: 'col-span-full flex flex-col items-center gap-2 rounded-lg border border-dashed py-14 text-center' }, [
        icon(imagesOnly ? 'image' : 'file', 'size-8 text-muted-foreground'),
        h('p', { class: 'text-sm font-medium' }, filtered ? 'Sonuç bulunamadı' : 'Henüz dosya yok'),
        h('p', { class: 'text-sm text-muted-foreground' }, filtered ? 'Farklı bir arama deneyin.' : 'Dosyaları buraya sürükleyin ya da "Yükle"ye tıklayın.'),
      ]));
      return;
    }
    grid.replaceChildren(...state.items.map(tile));
  }

  function tile(media, index) {
    const selected = multiple ? state.selection.has(media.id) : state.selected?.id === media.id;
    const isImage = media.type === 'image';

    return h('button', {
      type: 'button',
      role: 'option',
      'aria-selected': selected ? 'true' : 'false',
      title: media.name,
      class: `group relative aspect-square overflow-hidden rounded-md border bg-muted text-left outline-none transition
              focus-visible:ring-[3px] focus-visible:ring-ring/30 ${selected ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : 'hover:border-foreground/30'}`,
      onClick: (e) => (multiple ? clickMultiple(e, media, index) : select(selected ? null : media)),
      onDblclick: () => { select(media); onActivate?.(media); },
      onKeydown: (e) => { if (e.key === 'Enter' && selected) { e.preventDefault(); onActivate?.(media); } },
    }, [
      isImage
        ? h('img', { src: `${media.thumb_url}?v=${media.version}`, alt: media.alt ?? '', loading: 'lazy', class: 'size-full object-cover', draggable: 'false' })
        : h('div', { class: 'flex size-full flex-col items-center justify-center gap-2 text-muted-foreground' }, [
            icon('file', 'size-8'),
            h('span', { class: 'rounded-sm bg-background px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase text-foreground' }, media.extension),
          ]),
      h('div', { class: 'absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2 pb-1.5 pt-6 text-xs text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100' }, [
        h('p', { class: 'truncate font-medium' }, media.title || media.name),
        h('p', { class: 'text-white/70' }, isImage && media.width ? `${media.width}×${media.height} · ${media.size_label}` : media.size_label),
      ]),
      multiple
        // Onay kutusu: tıklayınca diğer seçimleri bozmadan ekler/çıkarır
        ? h('span', {
            role: 'checkbox',
            'aria-checked': selected ? 'true' : 'false',
            'aria-label': 'Seç',
            class: `absolute left-1.5 top-1.5 flex size-5 items-center justify-center rounded border shadow-sm transition
                    ${selected ? 'border-primary bg-primary text-primary-foreground' : `border-foreground/20 bg-background/90 ${state.selection.size ? '' : 'opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100'}`}`,
            onClick: (e) => { e.stopPropagation(); toggle(media, index); },
          }, selected ? icon('check', 'size-3.5') : null)
        : selected ? h('span', { class: 'absolute right-1.5 top-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground shadow' }, icon('check', 'size-3.5')) : null,
    ]);
  }

  function select(media) {
    if (multiple) return setSelection(media ? [media] : []);
    state.selected = media;
    renderGrid();
    onSelect?.(media);
  }

  // ---------------------------------------------------------------- çoklu seçim

  // Tık: tek seç (seçili ve tekse bırak) · Ctrl/Cmd+tık: ekle/çıkar · Shift+tık: son tıklanandan buna kadar ekle
  function clickMultiple(e, media, index) {
    if (e.shiftKey && state.anchor !== null) {
      const [from, to] = [Math.min(state.anchor, index), Math.max(state.anchor, index)];
      return addToSelection(state.items.slice(from, to + 1), index);
    }
    if (e.ctrlKey || e.metaKey) return toggle(media, index);
    const only = state.selection.size === 1 && state.selection.has(media.id);
    state.anchor = index;
    setSelection(only ? [] : [media]);
  }

  function toggle(media, index) {
    state.anchor = index;
    const next = new Map(state.selection);
    next.has(media.id) ? next.delete(media.id) : next.set(media.id, media);
    setSelection([...next.values()]);
  }

  function addToSelection(items, anchor = state.anchor) {
    state.anchor = anchor;
    const next = new Map(state.selection);
    items.forEach((m) => next.set(m.id, m));
    setSelection([...next.values()]);
  }

  function removeFromSelection(items) {
    const next = new Map(state.selection);
    items.forEach((m) => next.delete(m.id));
    setSelection([...next.values()]);
  }

  function setSelection(items) {
    const anchor = state.anchor;
    state.selection = new Map(items.map((m) => [m.id, m]));
    state.selected = items.length === 1 ? items[0] : null;
    state.anchor = items.length ? anchor : null;
    renderGrid();
    renderBar();
    onSelect?.(state.selected);
    onSelectionChange?.(items);
  }

  // ---------------------------------------------------------------- yükleme

  async function upload(files) {
    if (!files.length) return;
    const uploaded = [];

    for (const file of files) {
      const row = queueRow(file);
      const problem = precheck(file);
      if (problem) { row.fail(problem); continue; }

      try {
        const result = await mediaApi.upload(file, row.progress);
        uploaded.push(...result.data);
        row.done();
      } catch (err) {
        row.fail(err.message);
      }
    }

    if (!uploaded.length) return;
    toast(`${uploaded.length} dosya yüklendi.`);
    state.q = '';
    search.value = '';
    state.page = 1;
    await load();
    // İlk yükleneni seç (seçicide hemen "Seç" diyebilmek için)
    const first = state.items.find((m) => m.id === uploaded[0].id);
    if (first) select(first);
  }

  // Sunucu yine kontrol eder; bu sadece boşuna yükleme yapmamak için
  function precheck(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    const limits = state.limits;
    if (imagesOnly && !['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'].includes(ext)) return 'Sadece görsel yüklenebilir.';
    if (limits && !limits.types.includes(ext)) return `Bu dosya türüne izin yok (.${ext}).`;
    if (limits && file.size > limits.max_size) return `En fazla ${Math.round(limits.max_size / 1048576)} MB olabilir.`;
    return null;
  }

  function queueRow(file) {
    const bar = h('div', { class: 'h-full w-0 bg-primary transition-[width] duration-150' });
    const status = h('span', { class: 'shrink-0 text-xs tabular-nums text-muted-foreground' }, '0%');
    const row = h('div', { class: 'flex items-center gap-3 rounded-md border px-3 py-2 text-sm' }, [
      icon('upload', 'size-4 shrink-0 text-muted-foreground'),
      h('div', { class: 'min-w-0 flex-1' }, [
        h('p', { class: 'truncate' }, file.name),
        h('div', { class: 'mt-1 h-1 overflow-hidden rounded-full bg-muted' }, bar),
      ]),
      status,
    ]);
    queue.append(row);

    return {
      progress: (ratio) => {
        const pct = Math.round(ratio * 100);
        bar.style.width = `${pct}%`;
        status.textContent = pct >= 100 ? 'İşleniyor…' : `${pct}%`;
      },
      done: () => {
        bar.style.width = '100%';
        status.replaceChildren(icon('check', 'size-4 text-emerald-600'));
        setTimeout(() => row.remove(), 1200);
      },
      fail: (message) => {
        row.classList.add('border-destructive/40', 'bg-destructive/5');
        bar.parentElement.replaceWith(h('p', { class: 'mt-0.5 text-xs text-destructive' }, message));
        status.replaceChildren(h('button', {
          type: 'button', class: 'btn btn-ghost size-6 px-0', 'aria-label': 'Kapat', onClick: () => row.remove(),
        }, icon('x', 'size-3.5')));
      },
    };
  }

  load();

  return {
    element,
    selected: () => state.selected,
    selection: () => [...state.selection.values()],
    clearSelection: () => setSelection([]),
    reload: load,
    focusSearch: () => search.focus(),
    // Detaydan güncellenen kaydı ızgarada yenile
    replace(media) {
      state.items = state.items.map((m) => (m.id === media.id ? media : m));
      if (state.selected?.id === media.id) state.selected = media;
      if (state.selection.has(media.id)) state.selection.set(media.id, media);
      renderGrid();
    },
    removeItem(id) {
      return this.removeItems([id]);
    },
    // Silinen kayıtları seçimden çıkarıp listeyi yenile (sayfa tamamen boşaldıysa bir öncekine geç)
    async removeItems(ids) {
      const gone = new Set(ids);
      if (multiple) {
        const remaining = [...state.selection.values()].filter((m) => !gone.has(m.id));
        if (remaining.length !== state.selection.size) setSelection(remaining);
      } else if (gone.has(state.selected?.id)) {
        select(null);
      }
      if (state.items.length && state.items.every((m) => gone.has(m.id)) && state.page > 1) state.page--;
      await load();
    },
    // Listeyi sunucudan yeniden yükleyip verilen kaydı seç.
    //   keepPosition: true  -> bulunulan sayfa ve arama korunur (ör. orijinalin yerine kaydedilen görsel)
    //   keepPosition: false -> arama temizlenir, ilk sayfaya dönülür (ör. yeni oluşan kopya en başta)
    async reloadAndSelect(id, { keepPosition = false } = {}) {
      if (!keepPosition) {
        state.q = '';
        search.value = '';
        state.page = 1;
      }
      await load();
      const item = state.items.find((m) => m.id === id);
      select(item ?? null);
      // Seçilen kutucuğu görünür yap (sayfanın aşağısında kalmış olabilir)
      grid.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    },
  };
}
