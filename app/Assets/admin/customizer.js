/**
 * Görünüm > Özelleştir (canlı düzenleyici, WordPress Customizer gibi).
 *
 *  - Panelde bir alan değişince (yazarken kısa bir beklemeyle) tüm form taslak olarak sunucuya gider:
 *      POST /admin/appearance/customize/{tema}/draft   { values }
 *    Sonra önizleme yenilenir. Yenileme göz kırpmasın diye yeni sayfa arka planda ikinci bir iframe'de yüklenir,
 *    kaydırma konumu korunur ve hazır olunca öne alınır. Önizlemede gezilen sayfa da korunur.
 *  - Taslak sadece bu önizlemede görünür; "Yayınla" hepsini doğrulayıp kaydeder:
 *      POST /admin/appearance/customize/{tema}/publish  { ...values, _activate }
 *  - Panel: bölümler kayarak açılır (geri ile döner), "Gizle" ile kenara kapanır, cihaz boyutu seçilir.
 *  - Önizlemede bir bölümün "düzenle" düğmesine ya da işaretli bir yazıya/görsele tıklanınca (Theme::editable)
 *    önizleme postMessage gönderir; panel o bölümü açar ve alanı vurgular.
 * Sunucu: app/Controllers/Admin/AppearanceController.php
 */
import { config } from '../lib/app.js';
import { toast } from '../lib/toast.js';
import { confirmDialog } from '../lib/modal.js';
import { createSectionsEditor } from './customizer-sections.js';

const root = document.querySelector('[data-customizer]');
const form = root.querySelector('[data-form]');
const panel = root.querySelector('[data-panel]');
const stage = root.querySelector('[data-stage]');
const wrap = root.querySelector('[data-frame-wrap]');
const loading = root.querySelector('[data-loading]');
const status = root.querySelector('[data-status]');
const publishBtn = root.querySelector('[data-publish]');
const isActive = root.dataset.active === '1';

let frame = root.querySelector('[data-frame]');
let dirty = root.dataset.dirty === '1';
let draftTimer = null;
let draftSeq = 0;
let revealId = null;   // yenilemeden sonra önizlemede görünür yapılacak bölüm

// ================================================================ istek

async function post(url, body) {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': config.csrf,
    },
    body: JSON.stringify(body),
  });
  const payload = await response.json().catch(() => ({}));
  if (response.status === 401) window.location.assign(config.loginUrl);
  if (!response.ok) throw Object.assign(new Error(payload.error ?? `İstek başarısız (${response.status})`), { status: response.status, errors: payload.errors ?? {} });
  return payload;
}

// Formdaki tüm alanlar: checkbox '1' | '0', diğerleri değeri
function values() {
  const out = {};
  for (const el of form.elements) {
    if (!el.name || el.name === '_token') continue;
    out[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
  }
  return out;
}

// ================================================================ taslak + önizleme

form.addEventListener('input', onChange);
form.addEventListener('change', onChange);

function onChange(e) {
  if (e.target.matches('[data-theme-select]')) return;
  clearError(e.target.name);
  setDirty(true);
  // Yazı alanlarında yazmayı bitirmesini bekle, seçimlerde hemen
  const typing = e.type === 'input' && e.target.matches('input[type=text], input:not([type]), input[type=url], input[type=email], input[type=tel], input[type=number], textarea');
  clearTimeout(draftTimer);
  draftTimer = setTimeout(sendDraft, typing ? 450 : 80);
}

async function sendDraft() {
  const seq = ++draftSeq;
  setLoading(true);
  try {
    await post(root.dataset.draftUrl, { values: values() });
    if (seq === draftSeq) await refresh();   // arada yeni değişiklik olduysa onun yenilemesi yapsın
  } catch (err) {
    toast(err.message, 'error');
  } finally {
    if (seq === draftSeq) setLoading(false);
  }
}

// Arka planda yeni iframe yükle, kaydırmayı koru, hazır olunca değiştir
function refresh() {
  return new Promise((resolve) => {
    let href, scrollY = 0;
    try {
      href = frame.contentWindow.location.href;
      scrollY = frame.contentWindow.scrollY;
    } catch {
      href = frame.src;
    }

    const next = document.createElement('iframe');
    next.title = frame.title;
    next.className = frame.className + ' invisible';
    next.dataset.frame = '';
    next.src = href;
    next.addEventListener('load', () => {
      try { next.contentWindow.scrollTo(0, scrollY); } catch { /* farklı kökene gidildiyse */ }
      next.classList.remove('invisible');
      frame.remove();
      frame = next;
      watchFrame(frame);
      if (revealId) {
        try { next.contentDocument.querySelector(`[data-customize="section:${revealId}"]`)?.scrollIntoView({ block: 'start', behavior: 'smooth' }); } catch { /* */ }
        revealId = null;
      }
      resolve();
    }, { once: true });
    wrap.append(next);
  });
}

// Önizlemede dış siteye giden bağlantılar yeni sekmede açılsın (önizleme kaybolmasın)
function watchFrame(f) {
  f.addEventListener('load', () => {
    try {
      f.contentDocument.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (a && new URL(a.href, f.contentWindow.location.href).origin !== window.location.origin) {
          e.preventDefault();
          window.open(a.href, '_blank', 'noopener');
        }
      });
    } catch { /* erişilemiyor */ }
  });
}
watchFrame(frame);

function setLoading(on) {
  loading.classList.toggle('is-loading', on);
}

function setDirty(value) {
  dirty = value;
  status.textContent = value ? 'Yayınlanmamış değişiklikler var' : 'Özelleştiriliyor';
  publishBtn.disabled = !value && isActive;
}

// ================================================================ yayınla

publishBtn.addEventListener('click', publish);
document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (!publishBtn.disabled) publish(); }
});

async function publish() {
  clearTimeout(draftTimer);
  publishBtn.disabled = true;
  const label = publishBtn.textContent;
  publishBtn.textContent = 'Yayınlanıyor…';
  try {
    const result = await post(root.dataset.publishUrl, { ...values(), _activate: !isActive });
    dirty = false;
    toast(result.message);
    if (result.activated) {
      window.location.reload();   // artık etkin tema: üst çubuk ve düğme güncellensin
      return;
    }
    setDirty(false);
    await refresh();
  } catch (err) {
    if (err.status === 422 && Object.keys(err.errors).length) {
      showErrors(err.errors);
      toast('Bazı alanlar hatalı; işaretli bölümlere bakın.', 'error');
    } else {
      toast(err.message, 'error');
    }
    publishBtn.disabled = false;
  } finally {
    publishBtn.textContent = label;
  }
}

function showErrors(errors) {
  let first = null;
  for (const [name, message] of Object.entries(errors)) {
    const field = form.querySelector(`[data-field="${CSS.escape(name)}"]`);
    if (!field) continue;
    field.querySelector('.field-error')?.remove();
    field.querySelector('.field-help')?.classList.add('hidden');
    field.append(Object.assign(document.createElement('p'), { className: 'field-error', textContent: message }));
    field.querySelector('.input')?.classList.add('input-error');
    const view = field.closest('[data-view]').dataset.view;
    root.querySelector(`[data-error-dot="${CSS.escape(view)}"]`)?.classList.remove('hidden');
    first ??= view;
  }
  if (first) open(first);
}

function clearError(name) {
  if (!name) return;
  const field = form.querySelector(`[data-field="${CSS.escape(name)}"]`);
  if (!field) return;
  field.querySelector('.field-error')?.remove();
  field.querySelector('.field-help')?.classList.remove('hidden');
  field.querySelector('.input-error')?.classList.remove('input-error');
  const view = field.closest('[data-view]');
  if (view && !view.querySelector('.field-error')) root.querySelector(`[data-error-dot="${CSS.escape(view.dataset.view)}"]`)?.classList.add('hidden');
}

// ================================================================ panel gezinme

const views = Object.fromEntries([...root.querySelectorAll('[data-view]')].map((v) => [v.dataset.view, v]));
let current = 'root';

// Görünümler iç içe: her görünümün bir üstü var (data-parent, yoksa kök). Açılan sağdan gelir, üstü sola kayar.
const parentOf = (key) => (key === 'root' ? null : views[key]?.dataset.parent || 'root');

function show(key) {
  if (!views[key]) return;
  const chain = [];
  for (let k = key; k; k = parentOf(k)) chain.unshift(k);
  for (const [k, v] of Object.entries(views)) {
    const active = k === key;
    const behind = !active && chain.includes(k);
    v.classList.toggle('translate-x-full', !active && !behind && k !== 'root');
    v.classList.toggle('-translate-x-1/3', behind);
    if (active) v.classList.remove('invisible');
    else setTimeout(() => { if (current !== k) v.classList.add('invisible'); }, 300);
  }
  current = key;
  history.replaceState(null, '', key === 'root' ? location.pathname + location.search : `#${key}`);
}

function back() {
  const from = current;
  const parent = parentOf(from);
  if (parent) show(parent);
  sections.onBack?.(from);
}

function open(key, fieldKey = null) {
  // Bölüm örneği: 'section:a1b2c3' (alan: 'section:a1b2c3:title' ya da 'section:a1b2c3:items.0.title')
  if (key.startsWith('section:')) return sections.openInstance(key.slice(8), fieldKey ? fieldKey.split(':').slice(2).join(':') : null);
  const view = views[key];
  if (!view) return;
  show(key);
  const field = fieldKey && view.querySelector(`[data-field="${CSS.escape(fieldKey)}"]`);
  if (!field) {
    view.scrollTop = 0;
    view.querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
    return;
  }
  focusField(field);
}

// Alanı göster, imleci içine koy ve kısa bir süre vurgula
function focusField(field) {
  setTimeout(() => {
    field.scrollIntoView({ block: 'center', behavior: 'smooth' });
    (field.querySelector('input:not([type=hidden]):not([type=color]), textarea, select, .ql-editor') ?? field.querySelector('button'))?.focus({ preventScroll: true });
    field.animate([
      { boxShadow: '0 0 0 3px rgb(59 130 246 / .55)', backgroundColor: 'rgb(59 130 246 / .06)' },
      { boxShadow: '0 0 0 3px rgb(59 130 246 / 0)', backgroundColor: 'transparent' },
    ], { duration: 1400, easing: 'ease-out' });
  }, 320);
}

// Bölüm düzenleyicisi (ana sayfa bölümleri: sürükle-bırak, ekle, sil, alanlar)
const sections = createSectionsEditor({
  root,
  data: JSON.parse(document.getElementById('section-data').textContent),
  show,
  focusField,
  reveal: (id) => { revealId = id; },
});

// Önizlemeden gelen "bunu düzenle" isteği
window.addEventListener('message', (e) => {
  if (e.origin !== window.location.origin || e.data?.type !== 'modrix:customize') return;
  if (panel.style.marginLeft) collapse(false);
  showPreview(false);
  open(e.data.section, e.data.field ?? null);
});

root.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => open(b.dataset.open)));
root.querySelectorAll('[data-back]').forEach((b) => b.addEventListener('click', back));
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && current !== 'root' && !document.querySelector('[role="dialog"]') && !e.target.closest?.('input, textarea, .ql-editor')) back();
});
if (location.hash) {
  const key = decodeURIComponent(location.hash.slice(1));
  if (views[key] && !['section-edit', 'section-library'].includes(key)) open(key);
}

// Paneli gizle / göster (offcanvas)
function collapse(on) {
  panel.style.marginLeft = on ? `-${panel.offsetWidth}px` : '';
  root.querySelector('[data-expand]').classList.toggle('hidden', !on);
}
root.querySelector('[data-collapse]').addEventListener('click', () => {
  if (window.matchMedia('(max-width: 639px)').matches) return showPreview(true);
  collapse(true);
});
root.querySelector('[data-expand]').addEventListener('click', () => collapse(false));

// Telefonda panel ve önizleme yer değiştirir
function showPreview(on) {
  panel.classList.toggle('max-sm:hidden', on);
  stage.classList.toggle('max-sm:flex', on);
}
root.querySelector('[data-show-preview]').addEventListener('click', () => showPreview(true));
root.querySelector('[data-hide-preview]').addEventListener('click', () => showPreview(false));

// Cihaz boyutu
root.querySelectorAll('[data-device]').forEach((button) => button.addEventListener('click', () => {
  root.querySelectorAll('[data-device]').forEach((b) => b.toggleAttribute('data-on', b === button));
  const full = button.dataset.device === '100%';
  wrap.style.width = button.dataset.device;
  wrap.classList.toggle('my-4', !full);
  wrap.classList.toggle('rounded-lg', !full);
  wrap.classList.toggle('overflow-hidden', !full);
  wrap.style.height = full ? '' : 'calc(100% - 2rem)';
}));

// ================================================================ çıkış

async function leave(url) {
  if (dirty) {
    const ok = await confirmDialog({
      title: 'Yayınlanmamış değişiklikler',
      message: 'Değişiklikler yayınlanmadı. Çıkarsanız taslak bu oturumda kalır ama ziyaretçiler görmez.',
      confirmText: 'Yine de çık',
      danger: true,
    });
    if (!ok) return false;
  }
  dirty = false;
  window.location.assign(url);
  return true;
}

root.querySelectorAll('[data-close], [data-leave]').forEach((a) => a.addEventListener('click', (e) => {
  e.preventDefault();
  leave(a.href);
}));

root.querySelector('[data-theme-select]').addEventListener('change', async (e) => {
  const select = e.target;
  const ok = await leave(`${root.dataset.customizeUrl}?theme=${encodeURIComponent(select.value)}`);
  if (!ok) select.value = [...select.options].find((o) => o.defaultSelected)?.value ?? select.value;
});

window.addEventListener('beforeunload', (e) => { if (dirty) e.preventDefault(); });
