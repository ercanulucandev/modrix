/**
 * Görünüm > Özelleştir: bölüm düzenleyicisi (ana sayfa vb.).
 *
 *  - Alan listesi: bölümler sürükle-bırak ile sıralanır (SortableJS), göz simgesiyle gizlenir
 *  - "Bölüm ekle": kütüphaneden (tema / modül / genel bölümler) seçilen bölüm varsayılan değerleriyle eklenir
 *  - Bölüme tıklayınca ayarları açılır: alanlar şemaya göre kurulur (yazı, görsel, bağlantı, simge, renk,
 *    seçim, açma-kapama, zengin metin ve "repeater": kartlar/paketler/sorular gibi sıralanabilir listeler)
 *  - Her değişiklik gizli alana JSON olarak yazılır; customizer.js taslağı gönderir ve önizlemeyi yeniler
 *
 * Sunucu tarafı: core/Section.php (türler, temizleme, yazdırma), AppearanceController (taslak/yayın)
 */
import { h, icon as uiIcon } from '../lib/dom.js';
import { confirmDialog } from '../lib/modal.js';
import { openMediaPicker } from '../lib/media-picker.js';
import { createEditor } from '../lib/editor.js';

const SORTABLE = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/+esm';
let Sortable = null;
const sortableReady = import(SORTABLE).then((m) => { Sortable = m.default; }).catch(() => null);

export function createSectionsEditor({ root, data, show, focusField, reveal }) {
  const { types, icons, mediaBase } = data;
  const editView = root.querySelector('[data-view="section-edit"]');
  const libraryView = root.querySelector('[data-view="section-library"]');
  const editBody = editView.querySelector('[data-section-edit-body]');

  // Alanlar: { home: { key, label, instances, input, list } }
  const areas = {};

  let editing = null;   // { area, id }
  const openItems = new Set();   // açık repeater öğeleri: 'alan.index'

  // ================================================================ yardımcılar

  const svg = (name, cls = 'size-4') => {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('fill', 'none');
    el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-width', '2');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('class', cls);
    el.innerHTML = (icons[name] ?? icons.layers).svg;   // sunucudaki sabit simge seti (kullanıcı verisi değil)
    return el;
  };
  const eye = (hidden) => {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('viewBox', '0 0 24 24'); el.setAttribute('fill', 'none'); el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-width', '2'); el.setAttribute('stroke-linecap', 'round'); el.setAttribute('class', 'size-4');
    el.innerHTML = hidden
      ? '<path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="m2 2 20 20"/><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>'
      : '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
    return el;
  };
  const newId = () => Math.random().toString(16).slice(2, 10).padEnd(8, '0');
  const clone = (v) => JSON.parse(JSON.stringify(v));
  const find = (id) => {
    for (const [name, area] of Object.entries(areas)) {
      const instance = area.instances.find((i) => i.id === id);
      if (instance) return { area: name, instance };
    }
    return null;
  };

  // Listede görünen kısa açıklama: başlık benzeri ilk dolu yazı alanı
  function summary(instance) {
    const type = types[instance.type];
    for (const key of ['title', 'heading', 'name', 'text']) if (typeof instance.data[key] === 'string' && instance.data[key].trim()) return instance.data[key].replace(/\*/g, '');
    for (const [key, f] of Object.entries(type?.fields ?? {})) {
      if (['text', 'textarea'].includes(f.type) && typeof instance.data[key] === 'string' && instance.data[key].trim()) return instance.data[key];
    }
    return type?.description ?? '';
  }

  // Gizli alana yaz. viaEvent: değişiklik zaten bir input olayından geldiyse yeni olay gönderme (form onu yakalıyor)
  function commit(areaName, viaEvent = false) {
    const area = areas[areaName];
    area.input.value = JSON.stringify(area.instances);
    if (!viaEvent) area.input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // ================================================================ liste

  function renderList(areaName) {
    const area = areas[areaName];
    if (!area.instances.length) {
      area.list.replaceChildren(h('div', { class: 'rounded-lg border border-dashed px-4 py-8 text-center text-sm text-muted-foreground' }, 'Bu alanda bölüm yok. Aşağıdan ekleyin.'));
      return;
    }
    area.list.replaceChildren(...area.instances.map((instance) => {
      const type = types[instance.type];
      const toggle = h('button', { type: 'button', class: 'btn btn-ghost size-8 shrink-0 p-0 text-muted-foreground', title: instance.hidden ? 'Göster' : 'Gizle' }, eye(instance.hidden));
      toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        instance.hidden = !instance.hidden;
        renderList(areaName);
        commit(areaName);
      });
      const row = h('div', {
        class: `group flex cursor-pointer items-center gap-2 rounded-lg border bg-background p-1.5 pr-2 shadow-xs transition hover:border-foreground/25 ${instance.hidden ? 'opacity-55' : ''}`,
        'data-id': instance.id,
        role: 'button',
        tabindex: '0',
      }, [
        h('span', { class: 'drag flex size-8 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-accent active:cursor-grabbing', title: 'Sürükle' }, uiIcon('grip', 'size-4')),
        h('span', { class: 'flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-foreground' }, svg(type?.icon)),
        h('span', { class: 'min-w-0 flex-1' }, [
          h('span', { class: 'block truncate text-sm font-medium' }, type?.name ?? instance.type),
          h('span', { class: 'block truncate text-xs text-muted-foreground' }, instance.hidden ? 'Gizli' : summary(instance)),
        ]),
        toggle,
      ]);
      row.addEventListener('click', (e) => { if (!e.target.closest('.drag')) openInstance(instance.id); });
      row.addEventListener('keydown', (e) => { if (e.key === 'Enter') openInstance(instance.id); });
      return row;
    }));

    sortableReady.then(() => {
      if (!Sortable || area.list._sortable) return;
      area.list._sortable = Sortable.create(area.list, {
        handle: '.drag',
        animation: 150,
        ghostClass: 'opacity-40',
        onEnd: () => {
          const order = [...area.list.querySelectorAll('[data-id]')].map((el) => el.dataset.id);
          area.instances.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
          commit(areaName);
        },
      });
    });
  }

  // ================================================================ kütüphane

  function openLibrary(areaName) {
    libraryView.dataset.parent = `area-${areaName}`;
    const groups = {};
    for (const type of Object.values(types)) (groups[type.source] ??= []).push(type);
    const body = libraryView.querySelector('[data-section-library]');
    body.replaceChildren(...Object.entries(groups).map(([source, list]) => h('div', {}, [
      h('p', { class: 'mb-2 px-1 text-xs font-medium uppercase tracking-wider text-muted-foreground' }, source === 'Tema' ? 'Bu temanın bölümleri' : source),
      h('div', { class: 'grid gap-2' }, list.map((type) => {
        const card = h('button', { type: 'button', class: 'flex w-full items-start gap-3 rounded-lg border bg-background p-3 text-left transition hover:border-primary/40 hover:shadow-sm' }, [
          h('span', { class: 'flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground' }, svg(type.icon)),
          h('span', { class: 'min-w-0' }, [
            h('span', { class: 'block text-sm font-medium' }, type.name),
            type.description ? h('span', { class: 'mt-0.5 block text-xs leading-5 text-muted-foreground' }, type.description) : null,
          ]),
        ]);
        card.addEventListener('click', () => add(areaName, type));
        return card;
      })),
    ])));
    show('section-library');
  }

  function add(areaName, type) {
    const instance = { id: newId(), type: type.id, hidden: false, data: clone(type.defaults) };
    areas[areaName].instances.push(instance);
    renderList(areaName);
    reveal(instance.id);
    commit(areaName);
    openInstance(instance.id);
  }

  // ================================================================ bölüm ayarları

  function openInstance(id, fieldPath = null) {
    const found = find(id);
    if (!found) return;
    const { area: areaName, instance } = found;
    const type = types[instance.type];
    editing = { area: areaName, id };
    if (fieldPath?.includes('.')) openItems.add(fieldPath.split('.').slice(0, 2).join('.'));

    editView.dataset.parent = `area-${areaName}`;
    editView.querySelector('[data-section-edit-area]').textContent = areas[areaName].label;
    editView.querySelector('[data-section-edit-title]').textContent = type.name;
    renderEdit();
    show('section-edit');

    if (fieldPath) {
      const field = editBody.querySelector(`[data-path="${CSS.escape(fieldPath)}"]`);
      if (field) focusField(field);
    } else {
      editView.scrollTop = 0;
    }
  }

  function renderEdit() {
    const { area: areaName, id } = editing;
    const instance = areas[areaName].instances.find((i) => i.id === id);
    const type = types[instance.type];

    const toggle = editView.querySelector('[data-section-edit-toggle]');
    toggle.replaceChildren(eye(instance.hidden));
    toggle.title = instance.hidden ? 'Göster' : 'Gizle';
    toggle.onclick = () => { instance.hidden = !instance.hidden; renderEdit(); renderList(areaName); commit(areaName); };
    editView.querySelector('[data-section-edit-duplicate]').onclick = () => duplicate(areaName, instance);
    editView.querySelector('[data-section-edit-delete]').onclick = () => remove(areaName, instance);

    const fields = Object.entries(type.fields);
    editBody.replaceChildren(
      instance.hidden ? h('div', { class: 'rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900' }, 'Bu bölüm gizli; sitede görünmez.') : null,
      type.description ? h('p', { class: 'text-sm leading-6 text-muted-foreground' }, type.description) : null,
      ...(fields.length ? fields.map(([name, f]) => field(f, name, instance.data, areaName)) : [h('p', { class: 'text-sm text-muted-foreground' }, 'Bu bölümün ayarı yok.')]),
    );
  }

  function duplicate(areaName, instance) {
    const list = areas[areaName].instances;
    const copy = { ...clone(instance), id: newId() };
    list.splice(list.indexOf(instance) + 1, 0, copy);
    renderList(areaName);
    reveal(copy.id);
    commit(areaName);
    openInstance(copy.id);
  }

  async function remove(areaName, instance) {
    const ok = await confirmDialog({
      title: 'Bölüm silinsin mi?',
      message: `"${types[instance.type]?.name}" bölümü ve içindeki yazılar silinecek. Silmeden saklamak için göz simgesiyle gizleyebilirsiniz.`,
      confirmText: 'Sil',
      danger: true,
    });
    if (!ok) return;
    const list = areas[areaName].instances;
    list.splice(list.indexOf(instance), 1);
    renderList(areaName);
    commit(areaName);
    show(`area-${areaName}`);
  }

  // ================================================================ alanlar

  // f: alan tanımı, name: anahtar, data: değerlerin bulunduğu nesne (bölüm ya da repeater öğesi), path: önizlemeyle eşleşen yol
  function field(f, name, data, areaName, path = name) {
    const set = (value, viaEvent) => { data[name] = value; commit(areaName, viaEvent); };
    const wrap = (control, { inline = false } = {}) => h('div', { 'data-path': path, class: 'rounded-md' }, [
      inline ? null : h('label', { class: 'label' }, f.label),
      control,
      f.help ? h('p', { class: 'field-help text-xs' }, f.help) : null,
    ]);

    switch (f.type) {
      case 'textarea': {
        const el = h('textarea', { class: 'input', rows: f.rows ?? 3, maxlength: f.max ?? 5000, placeholder: f.placeholder ?? '' });
        el.value = data[name] ?? '';
        el.addEventListener('input', () => set(el.value, true));
        return wrap(el);
      }
      case 'richtext': {
        const box = h('div', { class: 'mt-2 overflow-hidden rounded-md border bg-background [&_.ql-editor]:min-h-36' });
        createEditor(box, { value: data[name] ?? '', placeholder: f.placeholder ?? 'Yazın…', onChange: (html) => set(html, false) });
        return wrap(box);
      }
      case 'select': {
        const el = h('select', { class: 'input' }, Object.entries(f.options ?? {}).map(([value, label]) => h('option', { value }, label)));
        el.value = String(data[name] ?? '');
        el.addEventListener('change', () => set(el.value, true));
        return wrap(el);
      }
      case 'checkbox': {
        const input = h('input', { type: 'checkbox', class: 'peer sr-only', checked: !!data[name] });
        input.addEventListener('change', () => set(input.checked, true));
        return wrap(h('label', { class: 'flex cursor-pointer items-center justify-between gap-4 rounded-md border bg-background px-3 py-2.5' }, [
          h('span', { class: 'text-sm font-medium' }, f.label),
          input,
          h('span', { class: 'relative inline-flex h-5 w-9 shrink-0 rounded-full bg-input transition-colors peer-checked:bg-primary after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-background after:shadow-sm after:transition-transform peer-checked:after:translate-x-4' }),
        ]), { inline: true });
      }
      case 'color': {
        const text = h('input', { class: 'input mt-0 w-28 font-mono text-xs', maxlength: 7, value: data[name] ?? '' });
        const picker = h('input', { type: 'color', class: 'h-9 w-11 cursor-pointer rounded-md border border-input bg-background p-1', value: /^#[0-9a-f]{6}$/i.test(data[name] ?? '') ? data[name] : '#000000' });
        picker.addEventListener('input', () => { text.value = picker.value; set(picker.value, true); });
        text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) { picker.value = text.value; set(text.value, true); } });
        return wrap(h('div', { class: 'mt-2 flex items-center gap-2' }, [picker, text]));
      }
      case 'number': {
        const el = h('input', { type: 'number', class: 'input w-32', min: f.min ?? null, max: f.max ?? null, step: f.step ?? 1 });
        el.value = data[name] ?? 0;
        el.addEventListener('input', () => set(el.value === '' ? 0 : Number(el.value), true));
        return wrap(el);
      }
      case 'image': return imageField(f, name, data, areaName, path, wrap);
      case 'icon': return iconField(f, name, data, areaName, wrap);
      case 'repeater': return repeaterField(f, name, data, areaName, path);
      default: {   // text, link
        const el = h('input', {
          class: `input ${f.type === 'link' ? 'font-mono text-xs' : ''}`,
          maxlength: f.max ?? (f.type === 'link' ? 500 : 300),
          placeholder: f.placeholder ?? (f.type === 'link' ? 'hakkimizda, #bolum ya da https://…' : ''),
          spellcheck: f.type === 'link' ? 'false' : null,
        });
        el.value = data[name] ?? '';
        el.addEventListener('input', () => set(el.value, true));
        return wrap(el);
      }
    }
  }

  function imageField(f, name, data, areaName, path, wrap) {
    const preview = h('button', { type: 'button', class: 'mt-2 flex aspect-video w-full items-center justify-center overflow-hidden rounded-md border bg-muted/60 text-sm text-muted-foreground transition hover:bg-muted' });
    const clear = h('button', { type: 'button', class: 'btn btn-ghost btn-sm mt-1 w-full text-destructive' }, 'Görseli kaldır');
    const paint = () => {
      const value = data[name];
      preview.replaceChildren(value ? h('img', { src: mediaBase + value, alt: '', class: 'size-full object-cover' }) : h('span', { class: 'flex items-center gap-2' }, [uiIcon('image', 'size-4'), 'Görsel seç']));
      preview.classList.toggle('border-dashed', !value);
      clear.classList.toggle('hidden', !value);
    };
    preview.addEventListener('click', async () => {
      const media = await openMediaPicker({ accept: 'image', title: f.label });
      if (!media) return;
      data[name] = media.path;
      paint();
      commit(areaName);
    });
    clear.addEventListener('click', () => { data[name] = ''; paint(); commit(areaName); });
    paint();
    return wrap(h('div', {}, [preview, clear]));
  }

  function iconField(f, name, data, areaName, wrap) {
    const current = h('button', { type: 'button', class: 'input flex items-center gap-2 text-left' });
    const grid = h('div', { class: 'mt-2 hidden grid grid-cols-7 gap-1 rounded-md border bg-background p-1.5' });
    const paint = () => current.replaceChildren(svg(data[name]), h('span', { class: 'flex-1 text-sm' }, icons[data[name]]?.label ?? 'Simge seç'), uiIcon('chevron_down', 'size-4 text-muted-foreground'));
    grid.append(...Object.entries(icons).map(([key, ic]) => {
      const b = h('button', { type: 'button', title: ic.label, 'aria-label': ic.label, class: 'flex aspect-square items-center justify-center rounded text-muted-foreground transition hover:bg-accent hover:text-foreground' }, svg(key));
      b.addEventListener('click', () => { data[name] = key; paint(); grid.classList.add('hidden'); commit(areaName); });
      return b;
    }));
    current.addEventListener('click', () => grid.classList.toggle('hidden'));
    paint();
    return wrap(h('div', {}, [current, grid]));
  }

  // Sıralanabilir liste: her öğe katlanır kart; ekle / çoğalt / sil, tutamaktan sürükle
  function repeaterField(f, name, data, areaName, path) {
    if (!Array.isArray(data[name])) data[name] = [];
    const items = data[name];
    const max = f.max ?? 24;
    const list = h('div', { class: 'mt-2 space-y-2' });
    const labelOf = (item, i) => {
      const key = f.item_label ?? Object.keys(f.fields).find((k) => ['text', 'textarea'].includes(f.fields[k].type));
      return (key && String(item[key] ?? '').replace(/\*/g, '').trim()) || `${f.item_name ?? 'Öğe'} ${i + 1}`;
    };
    const defaults = () => Object.fromEntries(Object.entries(f.fields).map(([k, sub]) => [k, sub.type === 'checkbox' ? !!sub.default : (sub.default ?? (sub.type === 'repeater' ? [] : ''))]));

    const rerender = () => {
      list.replaceChildren(...items.map((item, i) => {
        const key = `${path}.${i}`;
        const open = openItems.has(key);
        const title = h('span', { class: 'min-w-0 flex-1 truncate text-sm font-medium' }, labelOf(item, i));
        const body = h('div', { class: `space-y-4 border-t p-3 ${open ? '' : 'hidden'}` },
          Object.entries(f.fields).map(([sub, subDef]) => field(subDef, sub, item, areaName, `${key}.${sub}`)));
        body.addEventListener('input', () => { title.textContent = labelOf(item, i); });

        const toggle = () => {
          const nowOpen = body.classList.toggle('hidden') === false;
          nowOpen ? openItems.add(key) : openItems.delete(key);
          chevron.classList.toggle('rotate-180', nowOpen);
        };
        const chevron = uiIcon('chevron_down', `size-4 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`);
        const action = (label, iconName, fn, cls = '') => {
          const b = h('button', { type: 'button', class: `btn btn-ghost size-7 p-0 ${cls}`, title: label, 'aria-label': label }, uiIcon(iconName, 'size-3.5'));
          b.addEventListener('click', (e) => { e.stopPropagation(); fn(); });
          return b;
        };
        const head = h('div', { class: 'flex cursor-pointer items-center gap-1.5 px-1.5 py-1.5' }, [
          h('span', { class: 'drag flex size-7 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-accent', title: 'Sürükle' }, uiIcon('grip', 'size-3.5')),
          title,
          items.length < max ? action('Çoğalt', 'copy', () => { items.splice(i + 1, 0, clone(item)); rerender(); commit(areaName); }) : null,
          action('Sil', 'trash', () => { items.splice(i, 1); openItems.delete(key); rerender(); commit(areaName); }, 'text-destructive'),
          chevron,
        ]);
        head.addEventListener('click', (e) => { if (!e.target.closest('.drag')) toggle(); });
        return h('div', { class: 'rounded-md border bg-background', 'data-index': i, 'data-path': key }, [head, body]);
      }));

      addButton.disabled = items.length >= max;
      addButton.lastChild.textContent = items.length >= max ? `En fazla ${max}` : `${f.item_name ?? 'Öğe'} ekle`;

      sortableReady.then(() => {
        if (!Sortable || list._sortable) return;
        list._sortable = Sortable.create(list, {
          handle: '.drag',
          animation: 150,
          ghostClass: 'opacity-40',
          onEnd: (e) => {
            if (e.oldIndex === e.newIndex) return;
            items.splice(e.newIndex, 0, items.splice(e.oldIndex, 1)[0]);
            openItems.clear();
            rerender();
            commit(areaName);
          },
        });
      });
    };

    const addButton = h('button', { type: 'button', class: 'btn btn-secondary btn-sm mt-2 w-full border-dashed' }, [uiIcon('plus', 'size-3.5'), h('span', {}, '')]);
    addButton.addEventListener('click', () => {
      items.push(defaults());
      openItems.add(`${path}.${items.length - 1}`);
      rerender();
      commit(areaName);
    });
    rerender();

    return h('div', { 'data-path': path }, [
      h('div', { class: 'flex items-center justify-between' }, [h('span', { class: 'label' }, f.label), h('span', { class: 'text-xs text-muted-foreground' }, `${items.length}/${max}`)]),
      f.help ? h('p', { class: 'field-help text-xs' }, f.help) : null,
      list,
      addButton,
    ]);
  }

  // ================================================================ açılış
  // (en sonda: yukarıdaki yardımcılar ve değişkenler tanımlandıktan sonra)

  for (const [name, area] of Object.entries(data.areas)) {
    areas[name] = {
      ...area,
      input: root.querySelector(`[data-section-input="${name}"]`),
      list: root.querySelector(`[data-section-list="${name}"]`),
    };
    areas[name].input.value = JSON.stringify(area.instances);
    renderList(name);
    root.querySelector(`[data-section-add="${name}"]`).addEventListener('click', () => openLibrary(name));
  }

  return {
    openInstance,
    // Bölüm ayarlarından listeye dönünce özetleri tazele
    onBack(fromView) {
      if (fromView === 'section-edit' && editing) renderList(editing.area);
    },
  };
}
