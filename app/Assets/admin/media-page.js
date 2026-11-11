/**
 * Admin > Medya sayfası: medya tarayıcısı (sol) + seçilen dosyanın ayrıntıları (sağ).
 * Tek dosya: önizleme, bilgiler, adres (kopyala), başlık / alt metin düzenleme, düzenle, aç, sil.
 * Çoklu seçim (onay kutusu, Ctrl/Shift+tık, Ctrl+A): ZIP olarak indir, adresleri kopyala, toplu sil.
 */
import { config, url } from '../lib/app.js';
import { h, icon, formatDate } from '../lib/dom.js';
import { toast } from '../lib/toast.js';
import { confirmDialog } from '../lib/modal.js';
import { createMediaBrowser } from '../lib/media/media-browser.js';
import { mediaApi } from '../lib/media/media-api.js';
import { openImageEditor } from '../lib/media/image-editor.js';

const library = document.getElementById('media-library');
const details = document.getElementById('media-details');

// ---------------------------------------------------------------- toplu işlemler

const absolute = (media) => new URL(media.url, window.location.origin).href;
const totalSize = (items) => {
  const bytes = items.reduce((sum, m) => sum + (Number(m.size) || 0), 0);
  return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

// ZIP: normal form gönderimi (tarayıcı dosyayı indirir, sayfa değişmez)
function downloadZip(items) {
  const form = h('form', { method: 'post', action: url('admin/api/media/download'), class: 'hidden' }, [
    h('input', { type: 'hidden', name: '_token', value: config.csrf }),
    ...items.map((m) => h('input', { type: 'hidden', name: 'ids[]', value: m.id })),
  ]);
  document.body.append(form);
  form.submit();
  form.remove();
  toast(`${items.length} dosya ZIP olarak hazırlanıyor…`);
}

async function copyUrls(items) {
  try {
    await navigator.clipboard.writeText(items.map(absolute).join('\n'));
    toast(`${items.length} adres kopyalandı.`);
  } catch {
    toast('Panoya kopyalanamadı.', 'error');
  }
}

async function removeMany(items) {
  const ok = await confirmDialog({
    title: `${items.length} dosya silinsin mi?`,
    message: 'Seçilen dosyalar kalıcı olarak silinecek. İçerikte ya da ayarlarda kullanılıyorlarsa orada görünmez olurlar.',
    confirmText: `${items.length} dosyayı sil`,
    danger: true,
  });
  if (!ok) return;
  try {
    const { done, failed, message } = await mediaApi.removeMany(items.map((m) => m.id));
    await browser.removeItems(done);
    toast(message, Object.keys(failed ?? {}).length ? 'error' : 'success');
  } catch (err) {
    toast(err.message, 'error');
  }
}

const bulkActions = [
  { label: 'ZIP indir', icon: 'download', run: async (items) => downloadZip(items) },
  { label: 'Adresleri kopyala', icon: 'copy', run: copyUrls },
  { label: 'Sil', icon: 'trash', danger: true, run: removeMany },
];

const browser = createMediaBrowser({
  accept: 'all',
  perPage: 30,
  multiple: true,
  bulkActions,
  onSelectionChange: (items) => (items.length > 1 ? renderBulk(items) : renderDetails(items[0] ?? null)),
  onActivate: (media) => window.open(media.url, '_blank'),
});
library.append(browser.element);

// Birden çok dosya seçiliyken sağ panel: özet, küçük resimler, liste, işlemler
function renderBulk(items) {
  const images = items.filter((m) => m.type === 'image').slice(0, 9);
  details.replaceChildren(
    images.length
      ? h('div', { class: 'grid grid-cols-3 gap-1 rounded-t-lg border-b bg-muted p-1' },
          images.map((m) => h('img', { src: `${m.thumb_url}?v=${m.version}`, alt: '', class: 'aspect-square w-full rounded-sm object-cover' })))
      : null,
    h('div', { class: 'space-y-5 p-5' }, [
      h('div', {}, [
        h('p', { class: 'font-medium' }, `${items.length} dosya seçildi`),
        h('p', { class: 'mt-1 text-sm text-muted-foreground' }, `Toplam ${totalSize(items)} · ${items.filter((m) => m.type === 'image').length} görsel, ${items.filter((m) => m.type !== 'image').length} belge`),
      ]),
      h('ul', { class: 'max-h-48 space-y-1 overflow-y-auto text-sm' }, items.map((m) => h('li', { class: 'flex items-center gap-2' }, [
        icon(m.type === 'image' ? 'image' : 'file', 'size-3.5 shrink-0 text-muted-foreground'),
        h('span', { class: 'min-w-0 flex-1 truncate' }, m.name),
        h('span', { class: 'shrink-0 text-xs text-muted-foreground' }, m.size_label),
      ]))),
      h('div', { class: 'grid gap-2' }, [
        ...bulkActions.map((action) => h('button', {
          type: 'button',
          class: `btn btn-sm justify-start ${action.danger ? 'btn-danger-ghost' : 'btn-secondary'}`,
          onClick: () => action.run(items),
        }, [icon(action.icon, 'size-3.5'), action.label])),
        h('button', { type: 'button', class: 'btn btn-ghost btn-sm', onClick: () => browser.clearSelection() }, 'Seçimi kaldır'),
      ]),
      h('p', { class: 'text-xs leading-5 text-muted-foreground' }, 'İpucu: Ctrl/Cmd+tık ile ekleyip çıkarın, Shift+tık ile aralık seçin, Ctrl+A ile sayfadakilerin hepsini seçin, Esc ile bırakın.'),
    ]),
  );
}

function renderDetails(media) {
  if (!media) {
    details.replaceChildren(h('div', { class: 'px-6 py-16 text-center text-sm text-muted-foreground' }, 'Ayrıntıları görmek için bir dosya seçin.'));
    return;
  }

  const absoluteUrl = new URL(media.url, window.location.origin).href;
  const title = h('input', { class: 'input', value: media.title ?? '', maxlength: 255 });
  const alt = h('input', { class: 'input', value: media.alt ?? '', maxlength: 255, placeholder: 'Görselde ne var? (erişilebilirlik ve SEO)' });
  const saveBtn = h('button', { type: 'submit', class: 'btn btn-primary btn-sm' }, 'Kaydet');

  const save = async (e) => {
    e.preventDefault();
    saveBtn.disabled = true;
    try {
      const { data, message } = await mediaApi.update(media.id, { title: title.value.trim(), alt: alt.value.trim() });
      browser.replace(data);
      renderDetails(data);
      toast(message);
    } catch (err) {
      toast(err.message, 'error');
      saveBtn.disabled = false;
    }
  };

  const remove = async () => {
    const ok = await confirmDialog({
      title: 'Dosya silinsin mi?',
      message: `"${media.name}" kalıcı olarak silinecek. İçerikte ya da ayarlarda kullanılıyorsa orada görünmez olur.`,
      confirmText: 'Sil',
      danger: true,
    });
    if (!ok) return;
    try {
      const { message } = await mediaApi.remove(media.id);
      browser.removeItem(media.id);
      renderDetails(null);
      toast(message);
    } catch (err) {
      toast(err.message, 'error');
    }
  };

  // Görsel düzenleyici: kaydedince liste sunucudan yenilenir ve sonuç seçili gelir
  //   kopya  -> ilk sayfaya dönülür, yeni kopya seçilir (ayrıntılar onu gösterir)
  //   yerine -> sayfa ve arama korunur, aynı görsel güncellenmiş haliyle seçili kalır
  const edit = async () => {
    const result = await openImageEditor(media);
    if (!result) return;
    await browser.reloadAndSelect(result.media.id, { keepPosition: result.mode === 'replace' });
  };

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(absoluteUrl);
      toast('Adres kopyalandı.');
    } catch {
      urlInput.select();
    }
  };

  const urlInput = h('input', { class: 'input mt-0 font-mono text-xs', value: absoluteUrl, readonly: true, onFocus: (e) => e.target.select() });

  const row = (label, value) => h('div', { class: 'flex justify-between gap-4' }, [
    h('dt', { class: 'text-muted-foreground' }, label),
    h('dd', { class: 'truncate text-right' }, value),
  ]);

  details.replaceChildren(
    h('div', { class: 'flex aspect-video items-center justify-center overflow-hidden rounded-t-lg border-b bg-muted' },
      media.type === 'image'
        ? h('img', { src: `${media.url}?v=${media.version}`, alt: media.alt ?? '', class: 'max-h-full max-w-full object-contain' })
        : h('div', { class: 'flex flex-col items-center gap-2 text-muted-foreground' }, [
            icon('file', 'size-10'),
            h('span', { class: 'font-mono text-xs font-semibold uppercase' }, media.extension),
          ])),

    h('div', { class: 'space-y-5 p-5' }, [
      h('div', {}, [
        h('p', { class: 'break-all text-sm font-medium' }, media.name),
        h('dl', { class: 'mt-3 space-y-1.5 text-sm' }, [
          row('Tür', media.mime),
          row('Boyut', media.size_label),
          media.width ? row('Ölçü', `${media.width} × ${media.height} px`) : null,
          row('Yüklenme', formatDate(media.created_at)),
        ]),
      ]),

      h('div', { class: 'space-y-2' }, [
        h('label', { class: 'label' }, 'Adres'),
        h('div', { class: 'flex gap-2' }, [
          urlInput,
          h('button', { type: 'button', class: 'btn btn-secondary size-9 shrink-0 px-0', title: 'Kopyala', 'aria-label': 'Adresi kopyala', onClick: copy }, icon('copy')),
        ]),
      ]),

      h('form', { class: 'space-y-4', onSubmit: save }, [
        h('div', {}, [h('label', { class: 'label' }, 'Başlık'), title]),
        media.type === 'image' ? h('div', {}, [h('label', { class: 'label' }, 'Alt metin'), alt]) : null,
        h('div', { class: 'flex flex-wrap gap-2' }, [
          saveBtn,
          media.editable ? h('button', { type: 'button', class: 'btn btn-secondary btn-sm', onClick: edit }, [icon('crop', 'size-3.5'), 'Düzenle']) : null,
          h('a', { href: media.url, target: '_blank', class: 'btn btn-secondary btn-sm' }, [icon('external', 'size-3.5'), 'Aç']),
          h('button', { type: 'button', class: 'btn btn-danger-ghost btn-sm ml-auto', onClick: remove }, [icon('trash', 'size-3.5'), 'Sil']),
        ]),
      ]),
    ]),
  );
}
