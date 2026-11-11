/**
 * Görünüm > Menüler (WordPress'teki menü düzenleyici gibi).
 *
 *  - Soldan hazır bağlantı (modüllerin sayfaları, blog yazıları...) ya da özel bağlantı eklenir
 *  - Sağda öğeler sürükle-bırak ile sıralanır ve iç içe alınır (SortableJS, en fazla 3 seviye)
 *  - Öğeye tıklayınca adı, bağlantısı ve "yeni sekmede aç" düzenlenir
 *  - "Menüyü kaydet" tüm ağacı tek istekte gönderir (PUT /admin/api/menus/{id})
 *  - Menü konumları (temanın header/footer...) ayrı kaydedilir
 * Sunucu: app/Controllers/Admin/MenuApiController.php
 */
import { apiClient } from '../lib/app.js';
import { h, icon } from '../lib/dom.js';
import { confirmDialog } from '../lib/modal.js';
import { toast } from '../lib/toast.js';

const SORTABLE = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/+esm';
const MAX_DEPTH = 3;

const root = document.querySelector('[data-menus-page]');
const ref = Object.fromEntries([...root.querySelectorAll('[data-ref]')].map((el) => [el.dataset.ref, el]));
const api = apiClient('admin/api/menus');

let Sortable;
let data = { menus: [], locations: {}, links: [] };
let menu = null;     // düzenlenen menü { id, name }
let dirty = false;

// ================================================================ açılış

init();

async function init() {
  bindStatic();
  try {
    [{ default: Sortable }, data] = await Promise.all([import(SORTABLE), api.get('/')]);
  } catch (err) {
    toast(err.message ?? 'Menüler yüklenemedi', 'error');
    return;
  }
  renderLinks();
  renderLocations();
  const wanted = Number(new URLSearchParams(location.search).get('menu'));
  const first = data.menus.find((m) => m.id === wanted) ?? data.menus[0];
  first ? await openMenu(first.id) : showNoMenu();
}

function bindStatic() {
  ref.select.addEventListener('change', async () => {
    if (dirty && !(await confirmLeave())) { ref.select.value = menu.id; return; }
    openMenu(Number(ref.select.value));
  });
  ref.create.addEventListener('click', createMenu);
  ref.createFirst.addEventListener('click', createMenu);
  ref.save.addEventListener('click', saveMenu);
  ref.delete.addEventListener('click', deleteMenu);
  ref.saveLocations.addEventListener('click', saveLocations);
  ref.addLinks.addEventListener('click', addSelectedLinks);
  ref.name.addEventListener('input', () => setDirty());

  ref.customForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const label = ref.customLabel.value.trim();
    const url = ref.customUrl.value.trim();
    if (!label || !url) return toast('Ad ve bağlantı gerekli', 'error');
    addItems([{ label, url }]);
    ref.customForm.reset();
    ref.customLabel.focus();
  });

  // Hazır / özel bağlantı sekmeleri
  root.querySelectorAll('[data-tab]').forEach((tab) => tab.addEventListener('click', () => {
    root.querySelectorAll('[data-tab]').forEach((t) => {
      const on = t === tab;
      t.classList.toggle('border-primary', on);
      t.classList.toggle('font-medium', on);
      t.classList.toggle('border-transparent', !on);
      t.classList.toggle('text-muted-foreground', !on);
    });
    root.querySelectorAll('[data-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== tab.dataset.tab));
  }));

  // Ctrl+S
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's' && menu) { e.preventDefault(); saveMenu(); }
  });
  window.addEventListener('beforeunload', (e) => { if (dirty) e.preventDefault(); });
}

// ================================================================ menüler

function renderSelect() {
  ref.select.replaceChildren(...data.menus.map((m) => h('option', { value: m.id }, m.name)));
  if (menu) ref.select.value = menu.id;
}

function showNoMenu() {
  menu = null;
  ref.editor.classList.add('hidden');
  ref.addPanel.classList.add('opacity-50', 'pointer-events-none');
  ref.noMenu.classList.remove('hidden');
  renderSelect();
}

async function openMenu(id) {
  const result = await api.get(`/${id}`).catch((err) => { toast(err.message, 'error'); return null; });
  if (!result) return;

  menu = { id: result.menu.id, name: result.menu.name };
  ref.noMenu.classList.add('hidden');
  ref.editor.classList.remove('hidden');
  ref.addPanel.classList.remove('opacity-50', 'pointer-events-none');
  ref.name.value = menu.name;
  ref.tree.replaceChildren(...result.menu.items.map((item) => itemNode(item)));
  makeSortable(ref.tree);
  updateEmpty();
  renderSelect();
  setDirty(false);
  history.replaceState(null, '', `${location.pathname}?menu=${id}`);
}

async function createMenu() {
  if (dirty && !(await confirmLeave())) return;
  const name = prompt('Yeni menünün adı', data.menus.length ? 'Yeni menü' : 'Ana menü');
  if (!name?.trim()) return;
  try {
    const result = await api.post('/', { name: name.trim() });
    await reloadData();
    await openMenu(result.menu.id);
    toast('Menü oluşturuldu');
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function saveMenu() {
  if (!menu) return;
  ref.save.disabled = true;
  try {
    const result = await api.put(`/${menu.id}`, { name: ref.name.value.trim(), items: serialize(ref.tree) });
    menu.name = result.menu.name;
    ref.tree.replaceChildren(...result.menu.items.map((item) => itemNode(item)));
    makeSortable(ref.tree);
    await reloadData();
    setDirty(false);
    toast('Menü kaydedildi');
  } catch (err) {
    toast(err.message, 'error');
  } finally {
    ref.save.disabled = false;
  }
}

async function deleteMenu() {
  const ok = await confirmDialog({
    title: 'Menü silinsin mi?',
    message: `"${menu.name}" ve tüm öğeleri silinecek. Atandığı konumlar boş kalır.`,
    confirmText: 'Sil',
    danger: true,
  });
  if (!ok) return;
  try {
    await api.delete(`/${menu.id}`);
    setDirty(false);
    await reloadData();
    data.menus[0] ? openMenu(data.menus[0].id) : showNoMenu();
    toast('Menü silindi');
  } catch (err) {
    toast(err.message, 'error');
  }
}

async function reloadData() {
  data = await api.get('/');
  renderSelect();
  renderLocations();
}

// ================================================================ konumlar

function renderLocations() {
  const entries = Object.entries(data.locations);
  if (!entries.length) {
    ref.locations.replaceChildren(h('p', { class: 'text-sm text-muted-foreground' }, 'Bu tema menü konumu tanımlamıyor.'));
    return;
  }
  ref.locations.replaceChildren(...entries.map(([key, location]) => h('div', {}, [
    h('label', { class: 'label', for: `loc-${key}` }, location.label),
    h('select', { id: `loc-${key}`, class: 'input', 'data-location': key }, [
      h('option', { value: '' }, '— Menü yok —'),
      ...data.menus.map((m) => h('option', { value: m.id, selected: m.id === location.menu_id }, m.name)),
    ]),
  ])));
}

async function saveLocations() {
  const locations = Object.fromEntries([...ref.locations.querySelectorAll('[data-location]')].map((s) => [s.dataset.location, s.value ? Number(s.value) : null]));
  try {
    data.locations = (await api.put('/locations', { locations })).locations;
    toast('Konumlar kaydedildi');
  } catch (err) {
    toast(err.message, 'error');
  }
}

// ================================================================ bağlantı ekleme

function renderLinks() {
  const groups = Map.groupBy ? Map.groupBy(data.links, (l) => l.group) : data.links.reduce((m, l) => m.set(l.group, [...(m.get(l.group) ?? []), l]), new Map());
  ref.links.replaceChildren(...[...groups].map(([group, links]) => h('fieldset', {}, [
    h('legend', { class: 'mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground' }, group),
    ...links.map((link) => h('label', { class: 'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent' }, [
      h('input', { type: 'checkbox', class: 'size-4 accent-primary', 'data-link': JSON.stringify(link) }),
      h('span', { class: 'min-w-0 flex-1 truncate' }, link.label),
      h('span', { class: 'max-w-24 truncate font-mono text-[11px] text-muted-foreground' }, link.url),
    ])),
  ])));
}

function addSelectedLinks() {
  const boxes = [...ref.links.querySelectorAll('[data-link]:checked')];
  if (!boxes.length) return toast('Önce bağlantı seçin', 'error');
  addItems(boxes.map((b) => JSON.parse(b.dataset.link)));
  boxes.forEach((b) => { b.checked = false; });
}

function addItems(items) {
  for (const item of items) {
    const node = itemNode({ label: item.label, url: item.url, new_tab: false, children: [] });
    ref.tree.append(node);
    makeSortable(node.querySelector(':scope > ul'));
    node.animate([{ backgroundColor: 'var(--color-accent)' }, { backgroundColor: 'transparent' }], { duration: 900 });
  }
  updateEmpty();
  setDirty();
  toast(items.length > 1 ? `${items.length} öğe eklendi` : 'Öğe eklendi');
}

// ================================================================ öğe ağacı

function itemNode(item) {
  const label = h('input', { class: 'input', value: item.label, maxlength: 100 });
  const url = h('input', { class: 'input font-mono text-xs', value: item.url, maxlength: 500 });
  const newTab = h('input', { type: 'checkbox', class: 'size-4 accent-primary', checked: !!item.new_tab });
  const title = h('span', { class: 'truncate text-sm font-medium' }, item.label);
  const sub = h('span', { class: 'truncate font-mono text-xs text-muted-foreground' }, item.url);

  const panel = h('div', { class: 'hidden grid gap-3 border-t p-3 sm:grid-cols-2' }, [
    h('label', { class: 'block' }, [h('span', { class: 'label' }, 'Görünen ad'), label]),
    h('label', { class: 'block' }, [h('span', { class: 'label' }, 'Bağlantı'), url]),
    h('div', { class: 'flex items-center justify-between gap-3 sm:col-span-2' }, [
      h('label', { class: 'flex cursor-pointer items-center gap-2 text-sm' }, [newTab, 'Yeni sekmede aç']),
      h('button', { type: 'button', class: 'btn btn-danger-ghost btn-sm', onClick: () => removeItem(li) }, 'Kaldır'),
    ]),
  ]);

  const toggle = h('button', { type: 'button', class: 'btn btn-ghost btn-sm size-8 p-0', 'aria-label': 'Düzenle', 'aria-expanded': 'false' }, icon('chevron_down', 'size-4 transition-transform'));
  toggle.addEventListener('click', () => {
    const open = panel.classList.toggle('hidden') === false;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.firstChild.classList.toggle('rotate-180', open);
    if (open) label.focus();
  });

  const li = h('li', { class: 'menu-item' }, [
    h('div', { class: 'rounded-md border bg-background shadow-xs' }, [
      h('div', { class: 'flex items-center gap-2 py-1.5 pl-1.5 pr-1.5' }, [
        h('span', { class: 'drag flex size-8 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-accent active:cursor-grabbing', title: 'Sürükle' }, icon('grip', 'size-4')),
        h('span', { class: 'flex min-w-0 flex-1 flex-col' }, [title, sub]),
        toggle,
      ]),
      panel,
    ]),
    // Alt öğeler (boşken de bırakılabilsin diye her zaman var)
    h('ul', { class: 'menu-children ml-7 mt-2 space-y-2 empty:mt-0 empty:min-h-1.5' }, (item.children ?? []).map((child) => itemNode(child))),
  ]);
  li._fields = { label, url, newTab };

  label.addEventListener('input', () => { title.textContent = label.value || '(adsız)'; setDirty(); });
  url.addEventListener('input', () => { sub.textContent = url.value; setDirty(); });
  newTab.addEventListener('change', () => setDirty());
  return li;
}

function removeItem(li) {
  const children = li.querySelector(':scope > ul').children.length;
  if (children && !confirm('Bu öğenin alt öğeleri de kaldırılacak. Devam edilsin mi?')) return;
  li.remove();
  updateEmpty();
  setDirty();
}

function serialize(list) {
  return [...list.children].filter((li) => li._fields).map((li) => ({
    label: li._fields.label.value.trim(),
    url: li._fields.url.value.trim(),
    new_tab: li._fields.newTab.checked,
    children: serialize(li.querySelector(':scope > ul')),
  }));
}

// Kök ve tüm alt listeler sürüklenebilir; derinlik sınırı aşılmasın
function makeSortable(list) {
  if (!Sortable || !list) return;
  const lists = [list, ...list.querySelectorAll('ul.menu-children')];
  for (const ul of lists) {
    if (ul._sortable) continue;
    ul._sortable = Sortable.create(ul, {
      group: 'menu',
      handle: '.drag',
      animation: 150,
      fallbackOnBody: true,
      swapThreshold: 0.65,
      emptyInsertThreshold: 12,
      ghostClass: 'opacity-40',
      onMove: (evt) => depthOf(evt.to) + heightOf(evt.dragged) <= MAX_DEPTH,
      onEnd: () => { updateEmpty(); setDirty(); },
    });
  }
}

// Listenin seviyesi (kök = 0) ve öğenin kendisiyle birlikte alt seviye sayısı
function depthOf(ul) {
  let depth = 0;
  for (let el = ul; el && el !== ref.tree; el = el.parentElement) if (el.matches?.('ul.menu-children')) depth++;
  return depth;
}
function heightOf(li) {
  const kids = [...li.querySelector(':scope > ul').children];
  return 1 + (kids.length ? Math.max(...kids.map(heightOf)) : 0);
}

function updateEmpty() {
  ref.empty.classList.toggle('hidden', ref.tree.children.length > 0);
}

function setDirty(value = true) {
  dirty = value;
  ref.save.textContent = value ? 'Menüyü kaydet •' : 'Menüyü kaydet';
}

function confirmLeave() {
  return confirmDialog({ title: 'Kaydedilmemiş değişiklikler', message: 'Menüde kaydedilmemiş değişiklikler var. Kaydetmeden devam edilsin mi?', confirmText: 'Devam et', danger: true });
}
