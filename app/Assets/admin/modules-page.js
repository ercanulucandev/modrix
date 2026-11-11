/**
 * Admin > Modüller
 *  - ZIP yükleme: sürükle-bırak ya da seç -> XHR ile yükle (gerçek ilerleme yüzdesi)
 *    -> "Kuruluyor" (sunucu ZIP'i açıp doğrular, migration'ları çalıştırır) -> başarıda sayfa yenilenir.
 *  - Kur / Güncelle / Kaldır... düğmeleri: gönderilirken dönen simge, diğer işlemler kilitlenir.
 * Sunucu: ModuleController::upload (AJAX'a JSON döner).
 */
import { config } from '../lib/app.js';
import { h, icon } from '../lib/dom.js';
import { toast } from '../lib/toast.js';

const form = document.querySelector('[data-module-upload]');
const $ = (sel) => form.querySelector(sel);

const mb = (bytes) => `${(bytes / 1048576).toFixed(bytes < 1048576 ? 2 : 1)} MB`;
const spinner = (cls = 'size-5') => icon('loader', `${cls} animate-spin`);

// ================================================================ ZIP yükleme

if (form && !form.hasAttribute('data-disabled')) {
  const input = $('input[type=file]');
  const dropzone = $('[data-dropzone]');
  const status = $('[data-upload-status]');
  const ui = {
    icon: $('[data-upload-icon]'),
    name: $('[data-upload-name]'),
    percent: $('[data-upload-percent]'),
    text: $('[data-upload-text]'),
    track: $('[data-upload-track]'),
    bar: $('[data-upload-bar]'),
    retry: $('[data-upload-retry]'),
  };
  const maxSize = Number(form.dataset.max) || 0;
  let busy = false;

  $('[data-nojs]').remove(); // JS çalışıyor: dosya seçilince kendiliğinden yüklenir

  input.addEventListener('change', () => input.files[0] && upload(input.files[0]));

  // Sürükle-bırak (sayfanın herhangi bir yerine bırakılan dosya tarayıcıda açılmasın)
  let depth = 0;
  window.addEventListener('dragover', (e) => e.preventDefault());
  window.addEventListener('drop', (e) => e.preventDefault());
  dropzone.addEventListener('dragenter', (e) => { e.preventDefault(); depth++; dropzone.dataset.drag = ''; });
  dropzone.addEventListener('dragleave', () => { if (--depth <= 0) { depth = 0; delete dropzone.dataset.drag; } });
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    depth = 0;
    delete dropzone.dataset.drag;
    const file = e.dataTransfer.files[0];
    if (file) upload(file);
  });

  ui.retry.addEventListener('click', () => {
    status.classList.add('hidden');
    dropzone.classList.remove('hidden');
    input.value = '';
    input.click();
  });

  // Yükleme sürerken sayfadan çıkılırsa uyar
  window.addEventListener('beforeunload', (e) => { if (busy) e.preventDefault(); });

  function show(state, { text, percent = null, iconEl } = {}) {
    dropzone.classList.add('hidden');
    status.classList.remove('hidden');
    ui.text.textContent = text ?? '';
    ui.text.className = `mt-0.5 text-xs ${state === 'error' ? 'text-destructive' : 'text-muted-foreground'}`;
    ui.percent.textContent = percent === null ? '' : `%${percent}`;
    ui.icon.replaceChildren(iconEl);
    ui.icon.className = `flex size-10 shrink-0 items-center justify-center rounded-lg ${{
      error: 'bg-destructive/10 text-destructive',
      done: 'bg-emerald-500/10 text-emerald-600',
    }[state] ?? 'bg-muted text-muted-foreground'}`;

    // Çubuk: yüklemede yüzde, kurulumda belirsiz animasyon, sonunda dolu
    if (state === 'installing') ui.track.dataset.indeterminate = '';
    else delete ui.track.dataset.indeterminate;
    ui.bar.style.width = state === 'uploading' ? `${percent}%` : '100%';
    ui.bar.classList.toggle('bg-destructive', state === 'error');
    ui.bar.classList.toggle('bg-emerald-500', state === 'done');
    ui.bar.classList.toggle('bg-primary', state === 'uploading' || state === 'installing');
    ui.retry.classList.toggle('hidden', state !== 'error');
  }

  function fail(message) {
    busy = false;
    lockActions(false);
    show('error', { text: message, iconEl: icon('alert', 'size-5') });
    toast(message, 'error', 6000);
  }

  function upload(file) {
    if (busy) return;
    ui.name.textContent = file.name;

    if (!/\.zip$/i.test(file.name)) return fail('Sadece .zip dosyası yüklenebilir.');
    if (maxSize && file.size > maxSize) return fail(`Dosya çok büyük (${mb(file.size)}). En fazla ${mb(maxSize)}.`);

    busy = true;
    lockActions(true);
    show('uploading', { text: `0 / ${mb(file.size)}`, percent: 0, iconEl: spinner() });

    const data = new FormData();
    data.append('_token', config.csrf);
    data.append('module', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);

    xhr.upload.addEventListener('progress', (e) => {
      if (!e.lengthComputable) return;
      const percent = Math.min(100, Math.round((e.loaded / e.total) * 100));
      show('uploading', { text: `${mb(e.loaded)} / ${mb(e.total)}`, percent, iconEl: ui.icon.firstChild ?? spinner() });
    });

    // Dosya sunucuya ulaştı: artık sunucu açıyor, doğruluyor, kuruyor
    xhr.upload.addEventListener('load', () => {
      show('installing', { text: 'Kuruluyor… dosyalar doğrulanıyor ve veritabanı hazırlanıyor', iconEl: spinner() });
    });

    xhr.addEventListener('load', () => {
      let payload = {};
      try { payload = JSON.parse(xhr.responseText); } catch { /* HTML hata sayfası vb. */ }

      if (xhr.status === 401) return window.location.assign(config.loginUrl);
      if (xhr.status === 419) return fail('Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
      if (xhr.status === 413) return fail('Dosya sunucunun izin verdiği boyuttan büyük.');
      if (xhr.status < 200 || xhr.status >= 300) return fail(payload.error ?? `Yükleme başarısız (${xhr.status}).`);

      busy = false;
      show('done', { text: payload.message ?? 'Yüklendi.', iconEl: icon('check', 'size-5') });
      // Listeyi güncel haliyle göster (mesaj flash ile üstte de görünür)
      setTimeout(() => window.location.reload(), 900);
    });

    xhr.addEventListener('error', () => fail('Sunucuya ulaşılamadı. Bağlantınızı kontrol edin.'));
    xhr.addEventListener('abort', () => fail('Yükleme iptal edildi.'));

    xhr.send(data);
  }
}

// ================================================================ İşlem düğmeleri

function lockActions(locked) {
  for (const button of document.querySelectorAll('[data-module-action] button')) button.disabled = locked;
}

// Layout'taki onay (data-confirm) önce çalışır; iptal edildiyse defaultPrevented olur
document.addEventListener('submit', (e) => {
  const actionForm = e.target.closest('[data-module-action]');
  if (!actionForm || e.defaultPrevented) return;

  const button = actionForm.querySelector('button[data-busy]');
  // Düğmeyi gönderimden sonra kilitle (önce kilitlenirse form gönderilmez)
  requestAnimationFrame(() => {
    lockActions(true);
    const width = button.offsetWidth;
    button.style.minWidth = `${width}px`;
    button.replaceChildren(spinner('size-3.5'), h('span', {}, button.dataset.busy));
    button.classList.add('gap-1.5');
  });
});

// Geri tuşuyla sayfaya dönülünce (bfcache) düğmeler kilitli kalmasın
window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });
