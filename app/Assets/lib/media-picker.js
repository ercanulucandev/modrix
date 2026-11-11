/**
 * Medya seçici modalı. Her yerden çağrılabilir:
 *
 *   import { openMediaPicker } from '@app/lib/media-picker.js';
 *   const media = await openMediaPicker({ accept: 'image' });   // vazgeçilirse null
 *   if (media) img.src = media.url;
 *
 * Kütüphaneden seçilir ya da yeni dosya yüklenir (Yükle düğmesi / sürükle-bırak); yüklenen otomatik seçilir.
 * Çift tıklama ya da seçiliyken Enter doğrudan seçer.
 */
import { h } from './dom.js';
import { openModal } from './modal.js';
import { createMediaBrowser } from './media/media-browser.js';

export function openMediaPicker({ accept = 'image', title } = {}) {
  return new Promise((resolve) => {
    let result = null;

    const choose = (media) => {
      if (!media) return;
      result = media;
      modal.close({ force: true });
    };

    const info = h('p', { class: 'mr-auto hidden truncate text-sm text-muted-foreground sm:block' }, 'Bir dosya seçin.');
    const selectBtn = h('button', { type: 'button', class: 'btn btn-primary', disabled: true }, 'Seç');
    const cancelBtn = h('button', { type: 'button', class: 'btn btn-secondary' }, 'Vazgeç');

    const browser = createMediaBrowser({
      accept,
      perPage: 15,
      onSelect: (media) => {
        selectBtn.disabled = !media;
        info.textContent = media ? `${media.name} · ${media.size_label}${media.width ? ` · ${media.width}×${media.height}` : ''}` : 'Bir dosya seçin.';
      },
      onActivate: choose,
    });

    selectBtn.addEventListener('click', () => choose(browser.selected()));
    cancelBtn.addEventListener('click', () => modal.close());

    const modal = openModal({
      title: title ?? (accept === 'image' ? 'Görsel seç' : 'Dosya seç'),
      description: 'Kütüphaneden seçin ya da yeni dosya yükleyin (sürükle-bırak da olur).',
      size: 'xl',
      content: browser.element,
      footer: [info, cancelBtn, selectBtn],
      onClose: () => resolve(result),
    });
  });
}
