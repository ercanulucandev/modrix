/**
 * Görsel düzenleyici (Cropper.js 1.6 + canvas). Cropper sadece düzenleyici ilk açılınca CDN'den yüklenir.
 *
 *   import { openImageEditor } from '@app/lib/media/image-editor.js';
 *   const result = await openImageEditor(media);   // vazgeçilirse null
 *   // result: { media, mode: 'copy' | 'replace' }
 *
 * Özellikler: kırpma (serbest / hazır oranlar), 90° ve ince döndürme, yatay/dikey çevirme, yakınlaştırma,
 * parlaklık / kontrast / doygunluk / bulanıklık / siyah-beyaz / sepya (canlı önizleme), çıktı boyutu,
 * format (JPEG / WebP / PNG) ve kalite. Kaydet: kopya olarak ya da orijinalin yerine (aynı adres, aynı format).
 *
 * İşlem tarayıcıda yapılır; sunucuya sadece son görsel gönderilir (MediaApiController::edit).
 */
import { h, icon } from '../dom.js';
import { openModal } from '../modal.js';
import { toast } from '../toast.js';
import { mediaApi } from './media-api.js';

const CROPPER_VERSION = '1.6.2';
const CROPPER_JS = `https://cdn.jsdelivr.net/npm/cropperjs@${CROPPER_VERSION}/dist/cropper.min.js`;
const CROPPER_CSS = `https://cdn.jsdelivr.net/npm/cropperjs@${CROPPER_VERSION}/dist/cropper.min.css`;

const RATIOS = [['Serbest', NaN], ['Orijinal', 'original'], ['1:1', 1], ['4:3', 4 / 3], ['3:2', 3 / 2], ['16:9', 16 / 9], ['9:16', 9 / 16]];
const FORMATS = { jpeg: ['JPEG', 'image/jpeg', 'jpg'], webp: ['WebP', 'image/webp', 'webp'], png: ['PNG', 'image/png', 'png'] };
const WIDTHS = [['Orijinal', 0], ['1920', 1920], ['1280', 1280], ['800', 800]];
const DEFAULT_FILTERS = { brightness: 100, contrast: 100, saturate: 100, blur: 0, grayscale: false, sepia: false };

let loading = null;

function loadCropper() {
  if (window.Cropper) return Promise.resolve(window.Cropper);
  loading ??= new Promise((resolve, reject) => {
    const link = Object.assign(document.createElement('link'), { rel: 'stylesheet', href: CROPPER_CSS });
    const script = Object.assign(document.createElement('script'), { src: CROPPER_JS });
    script.onload = () => resolve(window.Cropper);
    script.onerror = () => { loading = null; reject(new Error('Görsel düzenleyici yüklenemedi. İnternet bağlantınızı kontrol edin.')); };
    document.head.append(link, script);
  });
  return loading;
}

export function openImageEditor(media) {
  return new Promise((resolve) => {
    let result = null;
    let cropper = null;
    let changed = false;

    const originalExt = media.extension === 'jpg' ? 'jpeg' : media.extension;   // jpeg | png | webp
    const state = {
      ratio: NaN,
      rotate90: 0,
      fine: 0,
      flipX: 1,
      flipY: 1,
      filters: { ...DEFAULT_FILTERS },
      width: 0,
      height: 0,
      lock: true,
      sizeTouched: false,
      format: originalExt,
      quality: 90,
    };

    // ------------------------------------------------------------- sahne (sol)

    const image = h('img', { src: `${media.url}?v=${media.version}`, alt: '', class: 'block max-w-full' });
    const stage = h('div', { class: 'relative h-[55vh] min-h-72 overflow-hidden rounded-md border bg-muted' }, [
      image,
      h('div', { class: 'absolute inset-0 flex items-center justify-center text-sm text-muted-foreground', 'data-loader': '' }, 'Düzenleyici yükleniyor…'),
    ]);

    const tool = (iconName, title, fn) => h('button', { type: 'button', class: 'btn btn-secondary size-9 px-0', title, 'aria-label': title, onClick: fn }, icon(iconName));
    const quickbar = h('div', { class: 'mt-3 flex flex-wrap items-center gap-2' }, [
      tool('zoom_in', 'Yakınlaştır', () => cropper?.zoom(0.1)),
      tool('zoom_out', 'Uzaklaştır', () => cropper?.zoom(-0.1)),
      h('span', { class: 'mx-1 h-6 w-px bg-border' }),
      tool('rotate_left', '90° sola döndür', () => rotate90(-90)),
      tool('rotate_right', '90° sağa döndür', () => rotate90(90)),
      tool('flip_h', 'Yatay çevir', () => { state.flipX *= -1; cropper?.scaleX(state.flipX); markChanged(); }),
      tool('flip_v', 'Dikey çevir', () => { state.flipY *= -1; cropper?.scaleY(state.flipY); markChanged(); }),
      h('button', { type: 'button', class: 'btn btn-ghost btn-sm ml-auto', onClick: resetAll }, 'Tümünü sıfırla'),
    ]);

    // ------------------------------------------------------------- ayarlar (sağ)

    const section = (title, children) => h('section', { class: 'space-y-3 border-b pb-5 last:border-0 last:pb-0' }, [
      h('h3', { class: 'text-sm font-medium' }, title), ...children,
    ]);

    // Kırpma oranı
    const ratioButtons = RATIOS.map(([label, value]) => h('button', {
      type: 'button', class: 'btn btn-sm', dataset: { ratio: String(value) },
      onClick: () => setRatio(value),
    }, label));
    const ratioGroup = h('div', { class: 'flex flex-wrap gap-1.5' }, ratioButtons);

    // İnce döndürme
    const fineValue = h('span', { class: 'text-xs tabular-nums text-muted-foreground' }, '0°');
    const fineSlider = slider(-45, 45, 0, 1, (v) => { state.fine = v; fineValue.textContent = `${v}°`; applyRotation(); markChanged(); });

    // Renk
    const filterControls = {};
    const filterRow = (key, label, min, max, unit = '%') => {
      const value = h('span', { class: 'text-xs tabular-nums text-muted-foreground' }, `${state.filters[key]}${unit}`);
      const input = slider(min, max, state.filters[key], 1, (v) => {
        state.filters[key] = v;
        value.textContent = `${v}${unit}`;
        applyFilters();
        markChanged();
      });
      filterControls[key] = { input, value, unit };
      return h('div', { class: 'space-y-1.5' }, [h('div', { class: 'flex justify-between text-sm' }, [h('span', {}, label), value]), input]);
    };
    const toggle = (key, label) => {
      const input = h('input', { type: 'checkbox', class: 'size-4 accent-[var(--color-primary)]', onChange: (e) => { state.filters[key] = e.target.checked; applyFilters(); markChanged(); } });
      filterControls[key] = { input };
      return h('label', { class: 'flex items-center gap-2 text-sm' }, [input, label]);
    };

    // Boyut
    const widthInput = h('input', { type: 'number', min: 1, max: 8000, class: 'input mt-0 w-full', 'aria-label': 'Genişlik' });
    const heightInput = h('input', { type: 'number', min: 1, max: 8000, class: 'input mt-0 w-full', 'aria-label': 'Yükseklik' });
    const lockInput = h('input', { type: 'checkbox', checked: true, class: 'size-4 accent-[var(--color-primary)]', onChange: (e) => { state.lock = e.target.checked; } });
    widthInput.addEventListener('input', () => sizeInput('width'));
    heightInput.addEventListener('input', () => sizeInput('height'));
    const widthPresets = h('div', { class: 'flex flex-wrap gap-1.5' }, WIDTHS.map(([label, w]) => h('button', {
      type: 'button', class: 'btn btn-ghost btn-sm', onClick: () => presetWidth(w),
    }, label)));

    // Çıktı
    const formatSelect = h('select', { class: 'input mt-0', onChange: (e) => { state.format = e.target.value; renderOutput(); markChanged(); } },
      Object.entries(FORMATS).map(([key, [label]]) => h('option', { value: key, selected: key === state.format }, label + (key === originalExt ? ' (orijinal)' : ''))));
    const qualityValue = h('span', { class: 'text-xs tabular-nums text-muted-foreground' }, `${state.quality}%`);
    const qualityRow = h('div', { class: 'space-y-1.5' }, [
      h('div', { class: 'flex justify-between text-sm' }, [h('span', {}, 'Kalite'), qualityValue]),
      slider(40, 100, state.quality, 1, (v) => { state.quality = v; qualityValue.textContent = `${v}%`; markChanged(); }),
    ]);

    const panel = h('div', { class: 'space-y-5 overflow-y-auto pr-1 lg:max-h-[calc(55vh+3.25rem)]' }, [
      section('Kırpma oranı', [ratioGroup]),
      section('İnce döndürme', [h('div', { class: 'flex justify-between text-sm' }, [h('span', {}, 'Açı'), fineValue]), fineSlider]),
      section('Renk', [
        filterRow('brightness', 'Parlaklık', 0, 200),
        filterRow('contrast', 'Kontrast', 0, 200),
        filterRow('saturate', 'Doygunluk', 0, 200),
        filterRow('blur', 'Bulanıklık', 0, 10, 'px'),
        h('div', { class: 'flex flex-wrap gap-4' }, [toggle('grayscale', 'Siyah-beyaz'), toggle('sepia', 'Sepya')]),
        h('button', { type: 'button', class: 'btn btn-ghost btn-sm -ml-2', onClick: resetFilters }, 'Renkleri sıfırla'),
      ]),
      section('Boyut (piksel)', [
        h('div', { class: 'grid grid-cols-[1fr_auto_1fr] items-center gap-2' }, [widthInput, h('span', { class: 'text-muted-foreground' }, '×'), heightInput]),
        h('label', { class: 'flex items-center gap-2 text-sm' }, [lockInput, 'Oranı koru']),
        h('div', { class: 'flex items-center gap-1 text-xs text-muted-foreground' }, ['Genişlik:', widthPresets]),
      ]),
      section('Çıktı', [formatSelect, qualityRow]),
    ]);

    // ------------------------------------------------------------- alt çubuk

    const info = h('p', { class: 'mr-auto hidden text-sm text-muted-foreground sm:block' });
    const cancelBtn = h('button', { type: 'button', class: 'btn btn-secondary', onClick: () => modal.close() }, 'Vazgeç');
    const copyBtn = h('button', { type: 'button', class: 'btn btn-secondary', onClick: () => save('copy') }, 'Kopya olarak kaydet');
    const replaceBtn = h('button', { type: 'button', class: 'btn btn-primary', onClick: () => save('replace') }, 'Orijinalin yerine kaydet');

    const modal = openModal({
      title: 'Görseli düzenle',
      description: `${media.name} · ${media.width}×${media.height} · ${media.size_label}`,
      size: '2xl',
      content: h('div', { class: 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]' }, [h('div', {}, [stage, quickbar]), panel]),
      footer: [info, cancelBtn, copyBtn, replaceBtn],
      beforeClose: () => !changed || window.confirm('Yaptığınız değişiklikler kaybolacak. Kapatılsın mı?'),
      onClose: () => { cropper?.destroy(); resolve(result); },
    });

    // ------------------------------------------------------------- başlat

    loadCropper().then((Cropper) => {
      const start = () => {
        cropper = new Cropper(image, {
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          responsive: true,
          checkOrientation: false,
          toggleDragModeOnDblclick: false,
          ready() {
            stage.querySelector('[data-loader]')?.remove();
            highlightRatio();
            syncSize(true);
            applyFilters();
          },
          crop() {
            if (cropper) syncSize(false);
          },
          cropend: markChanged,
          zoom: markChanged,
        });
      };
      image.complete ? start() : image.addEventListener('load', start, { once: true });
    }).catch((err) => {
      toast(err.message, 'error');
      modal.close({ force: true });
    });

    renderOutput();

    // ------------------------------------------------------------- işlemler

    function markChanged() { changed = true; }

    function setRatio(value) {
      state.ratio = value === 'original' ? media.width / media.height : value;
      cropper?.setAspectRatio(state.ratio);
      highlightRatio(value);
      markChanged();
    }

    function highlightRatio(value = 'NaN') {
      ratioButtons.forEach((b) => {
        const on = b.dataset.ratio === String(value);
        b.classList.toggle('btn-primary', on);
        b.classList.toggle('btn-secondary', !on);
      });
    }

    function rotate90(deg) {
      state.rotate90 = (state.rotate90 + deg) % 360;
      applyRotation();
      markChanged();
    }

    function applyRotation() {
      cropper?.rotateTo(state.rotate90 + state.fine);
    }

    // Canlı önizleme: Cropper'ın gösterdiği görsellere CSS filtresi (kaydederken aynı filtre canvas'a uygulanır)
    function filterCss() {
      const f = state.filters;
      return [
        `brightness(${f.brightness}%)`, `contrast(${f.contrast}%)`, `saturate(${f.saturate}%)`,
        f.blur ? `blur(${f.blur}px)` : '', f.grayscale ? 'grayscale(100%)' : '', f.sepia ? 'sepia(100%)' : '',
      ].filter(Boolean).join(' ');
    }

    function applyFilters() {
      stage.querySelectorAll('.cropper-container img').forEach((img) => { img.style.filter = filterCss(); });
    }

    function resetFilters() {
      state.filters = { ...DEFAULT_FILTERS };
      for (const [key, c] of Object.entries(filterControls)) {
        if (c.input.type === 'checkbox') c.input.checked = false;
        else { c.input.value = state.filters[key]; c.value.textContent = `${state.filters[key]}${c.unit}`; }
      }
      applyFilters();
    }

    function resetAll() {
      cropper?.reset();
      Object.assign(state, { rotate90: 0, fine: 0, flipX: 1, flipY: 1, sizeTouched: false });
      fineSlider.value = 0;
      fineValue.textContent = '0°';
      cropper?.rotateTo(0);
      cropper?.scale(1, 1);
      setRatio(NaN);
      resetFilters();
      changed = false;
    }

    // Kırpma alanı değişince boyut alanları (kullanıcı elle girmediyse) kırpma boyutunu gösterir
    function syncSize(force) {
      if (!cropper || (state.sizeTouched && !force)) return renderOutput();
      const data = cropper.getData(true);
      state.width = Math.max(1, data.width);
      state.height = Math.max(1, data.height);
      widthInput.value = state.width;
      heightInput.value = state.height;
      renderOutput();
    }

    function sizeInput(which) {
      state.sizeTouched = true;
      const data = cropper?.getData(true);
      const ratio = data ? data.width / data.height : state.width / state.height;
      const w = Math.max(1, Math.min(8000, Number(widthInput.value) || 1));
      const hgt = Math.max(1, Math.min(8000, Number(heightInput.value) || 1));
      if (which === 'width') {
        state.width = w;
        if (state.lock) { state.height = Math.max(1, Math.round(w / ratio)); heightInput.value = state.height; }
      } else {
        state.height = hgt;
        if (state.lock) { state.width = Math.max(1, Math.round(hgt * ratio)); widthInput.value = state.width; }
      }
      renderOutput();
      markChanged();
    }

    function presetWidth(w) {
      if (!cropper) return;
      if (!w) { state.sizeTouched = false; return syncSize(true); }
      widthInput.value = w;
      sizeInput('width');
    }

    function renderOutput() {
      const [label] = FORMATS[state.format];
      info.textContent = state.width ? `Çıktı: ${state.width}×${state.height} · ${label}` : '';
      qualityRow.classList.toggle('hidden', state.format === 'png');
      const sameFormat = state.format === originalExt;
      replaceBtn.disabled = !sameFormat;
      replaceBtn.title = sameFormat ? 'Aynı adres korunur; kullanıldığı yerler güncellenir' : `Yerine kaydetmek için format ${FORMATS[originalExt][0]} olmalı`;
    }

    async function render() {
      const [, mime] = FORMATS[state.format];
      const cropped = cropper.getCroppedCanvas({
        width: state.width,
        height: state.height,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
        fillColor: state.format === 'jpeg' ? '#ffffff' : 'transparent',   // JPEG şeffaflık desteklemez
      });
      if (!cropped) throw new Error('Görsel işlenemedi.');

      const out = document.createElement('canvas');
      out.width = cropped.width;
      out.height = cropped.height;
      const ctx = out.getContext('2d');
      ctx.filter = filterCss() || 'none';
      ctx.drawImage(cropped, 0, 0);

      return new Promise((res, rej) => out.toBlob(
        (blob) => (blob ? res(blob) : rej(new Error('Görsel oluşturulamadı.'))),
        mime,
        state.format === 'png' ? undefined : state.quality / 100,
      ));
    }

    async function save(mode) {
      if (!cropper) return;
      const buttons = [copyBtn, replaceBtn, cancelBtn];
      const label = mode === 'replace' ? replaceBtn : copyBtn;
      const original = label.textContent;
      buttons.forEach((b) => { b.disabled = true; });
      label.replaceChildren(icon('loader', 'size-4 animate-spin'), 'Kaydediliyor…');
      modal.setBusy(true);

      try {
        const blob = await render();
        const base = media.name.replace(/\.[^.]+$/, '');
        const response = await mediaApi.edit(media.id, blob, {
          mode,
          filename: `${base}.${FORMATS[state.format][2]}`,
          onProgress: (r) => { label.lastChild.textContent = r < 1 ? `Yükleniyor %${Math.round(r * 100)}` : 'İşleniyor…'; },
        });
        result = { media: response.data, mode: response.mode };
        toast(response.message);
        modal.setBusy(false);
        modal.close({ force: true });
      } catch (err) {
        toast(err.message, 'error');
        modal.setBusy(false);
        buttons.forEach((b) => { b.disabled = false; });
        label.replaceChildren(original);
        renderOutput();
      }
    }
  });
}

function slider(min, max, value, step, onInput) {
  const input = h('input', { type: 'range', min, max, step, value, class: 'w-full accent-[var(--color-primary)]' });
  input.addEventListener('input', () => onInput(Number(input.value)));
  return input;
}
