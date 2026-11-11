/**
 * Admin > Ayarlar: görsel alanları (logo, favicon, paylaşım görseli) medya kütüphanesinden seçilir.
 * Her alan: [data-media-field] içinde gizli input (dosya yolu), önizleme, "Kütüphaneden seç" ve "Kaldır".
 * Değer form gönderilince kaydedilir; sunucu yolun kütüphanede bir görsel olduğunu doğrular.
 */
import { h } from '../lib/dom.js';
import { openMediaPicker } from '../lib/media-picker.js';

for (const field of document.querySelectorAll('[data-media-field]')) {
  const input = field.querySelector('[data-media-value]');
  const preview = field.querySelector('[data-media-preview]');
  const name = field.querySelector('[data-media-name]');
  const clear = field.querySelector('[data-media-clear]');
  const label = field.querySelector('.label')?.textContent ?? 'Görsel';

  const set = (media) => {
    input.value = media?.path ?? '';
    preview.replaceChildren(media
      ? h('img', { src: `${media.thumb_url ?? media.url}?v=${media.version}`, alt: '', class: 'max-h-full max-w-full object-contain' })
      : h('span', { class: 'text-xs text-muted-foreground' }, 'Yok'));
    name.replaceChildren(media ? media.name : h('span', { class: 'text-muted-foreground' }, 'Görsel seçilmedi'));
    clear.classList.toggle('hidden', !media);
    // Canlı önizleme (Görünüm > Özelleştir) değişikliği duysun
    input.dispatchEvent(new Event('change', { bubbles: true }));
  };

  field.querySelector('[data-media-choose]').addEventListener('click', async () => {
    const media = await openMediaPicker({ accept: 'image', title: `${label} seç` });
    if (media) set(media);
  });

  clear.addEventListener('click', () => set(null));
}

// ---- Çoklu görsel alanı (koleksiyonlar: portfolyo vb.): ekle, kaldır, sürükleyerek sırala
for (const field of document.querySelectorAll('[data-gallery-field]')) {
  const input = field.querySelector('[data-gallery-value]');
  const list = field.querySelector('[data-gallery-list]');
  const sync = () => { input.value = JSON.stringify([...list.children].map((el) => el.dataset.path)); };
  const tile = (path, url) => h('div', { class: 'group relative aspect-square overflow-hidden rounded-md border bg-muted', 'data-path': path }, [
    h('img', { src: url, alt: '', class: 'size-full cursor-grab object-cover' }),
    h('button', { type: 'button', class: 'absolute right-1 top-1 rounded bg-black/60 px-1.5 text-xs text-white', 'data-gallery-remove': '', 'aria-label': 'Kaldır' }, '✕'),
  ]);

  field.querySelector('[data-gallery-add]').addEventListener('click', async () => {
    const media = await openMediaPicker({ accept: 'image', title: 'Görsel ekle' });
    if (!media || [...list.children].some((el) => el.dataset.path === media.path)) return;
    list.append(tile(media.path, media.thumb_url ?? media.url));
    sync();
  });
  list.addEventListener('click', (e) => {
    const remove = e.target.closest('[data-gallery-remove]');
    if (remove) { remove.parentElement.remove(); sync(); }
  });
  import('https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/+esm')
    .then(({ default: Sortable }) => Sortable.create(list, { animation: 150, onEnd: sync }))
    .catch(() => {});
}
