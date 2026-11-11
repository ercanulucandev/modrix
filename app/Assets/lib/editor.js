/**
 * HTML editör (Quill 2). Quill sadece ilk editör açılınca CDN'den yüklenir.
 *
 *   const editor = await createEditor(container, {
 *     value: '<p>Merhaba</p>',      // HTML ya da eski düz metin (paragraflara çevrilir)
 *     placeholder: 'Yazın…',
 *     onChange: (html) => { ... },
 *   });
 *   editor.getHtml();  editor.setHtml(html);  editor.enable(false);  editor.focus();
 *
 * Görsele tıklayınca araç çubuğu: boyut (küçük/orta/büyük), hizalama (sol/orta/sağ, yazı yanından akar),
 * alt metin, değiştir (medya kütüphanesi), düzenle (kırp/döndür), sil. Sınıflar: Html::IMAGE_CLASSES.
 *
 * Özellikler
 *   - Akıllı yapıştırma: düz metin olarak gelen HTML kodu biçimli içerik olarak eklenir
 *     (imleç kod bloğundaysa kod olduğu gibi kalır).
 *   - HTML kaynağı (</> düğmesi): içeriği HTML olarak düzenle, tekrar basınca biçimli görünüme dön.
 *
 * Çıktı anlamsal HTML'dir (p, h2, h3, strong, em, u, s, a, blockquote, pre, ul, ol, li).
 * Sunucu her kaydı yine Html::sanitize ile temizler; istemciye güvenilmez.
 */

import { openMediaPicker } from './media-picker.js';

const QUILL_VERSION = '2.0.3';
const QUILL_JS = `https://cdn.jsdelivr.net/npm/quill@${QUILL_VERSION}/dist/quill.js`;
const QUILL_CSS = `https://cdn.jsdelivr.net/npm/quill@${QUILL_VERSION}/dist/quill.snow.css`;
const THEME_CSS = new URL('../css/editor.css', import.meta.url).href;   // editörün panel teması

const TOOLBAR = [
  [{ header: [2, 3, false] }],
  ['bold', 'italic', 'underline', 'strike'],
  ['link', 'image', 'blockquote', 'code-block'],
  [{ list: 'ordered' }, { list: 'bullet' }],
  ['clean'],
  ['html'],
];

// Araç çubuğu düğmelerinin Türkçe ipuçları
const TITLES = {
  '.ql-header': 'Başlık', '.ql-bold': 'Kalın', '.ql-italic': 'İtalik', '.ql-underline': 'Altı çizili',
  '.ql-strike': 'Üstü çizili', '.ql-link': 'Link', '.ql-blockquote': 'Alıntı', '.ql-code-block': 'Kod bloğu',
  '.ql-list[value="ordered"]': 'Numaralı liste', '.ql-list[value="bullet"]': 'Madde işaretli liste',
  '.ql-clean': 'Biçimi temizle', '.ql-html': 'HTML kaynağı', '.ql-image': 'Görsel ekle (medya kütüphanesi)',
};

const HTML_ICON = '<svg viewBox="0 0 18 18"><polyline class="ql-even ql-stroke" points="5 7 3 9 5 11"></polyline>'
  + '<polyline class="ql-even ql-stroke" points="13 7 15 9 13 11"></polyline><line class="ql-stroke" x1="10" x2="8" y1="5" y2="13"></line></svg>';

// Düz metin bir HTML belgesine/parçasına benziyor mu? (en az bir açılış-kapanış etiketi ya da blok etiketi)
const LOOKS_LIKE_HTML = /^\s*<(!doctype|html|body|div|p|h[1-6]|ul|ol|li|table|section|article|blockquote|pre|span|strong|em|a|img|br)\b[^>]*>[\s\S]*$/i;

let loading = null;

function loadQuill() {
  if (window.Quill) return Promise.resolve(window.Quill);

  loading ??= new Promise((resolve, reject) => {
    for (const href of [QUILL_CSS, THEME_CSS]) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      document.head.append(link);
    }
    const script = document.createElement('script');
    script.src = QUILL_JS;
    script.onload = () => resolve(window.Quill);
    script.onerror = () => {
      loading = null;
      reject(new Error('Editör yüklenemedi. İnternet bağlantınızı kontrol edin.'));
    };
    document.head.append(script);
  });
  return loading;
}

// Görsel: Quill'in görsel biçimi sadece alt/width/height tutar; boyut ve hizalama sınıflarını (img-md img-left...)
// da tutması için genişletilir. Sunucu sadece Html::IMAGE_CLASSES'taki sınıfları bırakır.
const IMAGE_SIZES = { '': 'Orijinal', 'img-sm': 'Küçük', 'img-md': 'Orta', 'img-lg': 'Büyük' };
const IMAGE_ALIGNS = { '': 'Hizasız', 'img-left': 'Sola (yazı yanından akar)', 'img-center': 'Ortaya', 'img-right': 'Sağa (yazı yanından akar)' };
const ALIGN_ICONS = {
  '': '<path d="M3 5h18M3 19h18"/><rect x="6" y="9" width="12" height="6" rx="1"/>',
  'img-left': '<rect x="3" y="5" width="8" height="8" rx="1"/><path d="M14 6h7M14 10h7M3 17h18"/>',
  'img-center': '<path d="M3 4h18M3 20h18"/><rect x="7" y="8" width="10" height="8" rx="1"/>',
  'img-right': '<rect x="13" y="5" width="8" height="8" rx="1"/><path d="M3 6h7M3 10h7M3 17h18"/>',
};
let imageRegistered = false;

function registerImage(Quill) {
  if (imageRegistered) return;
  const Image = Quill.import('formats/image');
  class ModrixImage extends Image {
    static formats(node) {
      const formats = super.formats(node);
      if (node.getAttribute('class')) formats.class = node.getAttribute('class');
      return formats;
    }
    format(name, value) {
      if (name !== 'class') return super.format(name, value);
      value ? this.domNode.setAttribute('class', value) : this.domNode.removeAttribute('class');
    }
  }
  Quill.register(ModrixImage, true);
  imageRegistered = true;
}

export async function createEditor(container, { value = '', placeholder = '', onChange } = {}) {
  const Quill = await loadQuill();
  registerImage(Quill);
  let sourceMode = false;
  let source = null;   // HTML kaynağı textarea'sı (ilk kullanımda oluşur)

  const quill = new Quill(container, {
    theme: 'snow',
    placeholder,
    modules: {
      // image: Quill'in kendi düğmesi görseli base64 olarak gömer; onun yerine medya kütüphanesinden seçilir
      toolbar: { container: TOOLBAR, handlers: { html: () => toggleSource(), image: () => insertImage() } },
      clipboard: { matchVisual: false },
    },
  });

  const wrapper = container.parentElement;
  wrapper.classList.add('rich-editor');   // editor.css stilleri (tam sayfa düzenleyicilerde de)
  const toolbar = container.previousElementSibling;
  toolbar.querySelector('.ql-html').innerHTML = HTML_ICON;
  for (const [selector, title] of Object.entries(TITLES)) {
    toolbar.querySelectorAll(selector).forEach((el) => el.setAttribute('title', title));
  }

  // ---------------------------------------------------------------- değer

  const quillHtml = () => {
    const hasEmbed = quill.getContents().ops.some((op) => typeof op.insert === 'object');   // sadece görsel olabilir
    if (quill.getText().trim() === '' && !hasEmbed) return '';
    // getSemanticHTML boşlukları &nbsp; yapar; normal boşluğa çevir ki satırlar düzgün kırılsın.
    // Görsel adresindeki önbellek eki (?v=...) kaydedilmez (sadece düzenleyicide yenisini göstermek için)
    return quill.getSemanticHTML().replace(/&nbsp;/g, ' ').replace(/(<img[^>]*?src="[^"?]*)\?[^"]*"/g, '$1"').trim();
  };

  const getHtml = () => (sourceMode ? source.value.trim() : quillHtml());

  const setHtml = (html) => {
    quill.setContents(quill.clipboard.convert({ html: toHtml(html) }), 'silent');
    quill.history.clear();
  };

  setHtml(value);
  quill.on('text-change', () => onChange?.(getHtml()));

  // ---------------------------------------------------------------- akıllı yapıştırma

  // Düz metni HTML koduna benziyorsa (ör. "<p>Merhaba</p>") kod değil, biçimli içerik olarak eklenir.
  // Panodaki text/html'e bakılmaz: VS Code ya da bir sitenin kod kutusundan kopyalanınca orada
  // kodun RENKLİ GÖRÜNTÜSÜ olur ve Quill onu yazı olarak ekler (sorunun sebebi buydu).
  // Normal bir sayfadan içerik kopyalanınca düz metin HTML'e benzemez -> Quill'in kendi yapıştırması çalışır.
  quill.root.addEventListener('paste', (e) => {
    const text = e.clipboardData?.getData('text/plain') ?? '';
    if (!LOOKS_LIKE_HTML.test(text)) return;

    const range = quill.getSelection(true);
    if (quill.getFormat(range)['code-block']) return;                 // kod bloğunda: kodu olduğu gibi yapıştır

    e.preventDefault();
    e.stopImmediatePropagation();                                     // Quill'in kendi yapıştırması çalışmasın

    if (range.length) quill.deleteText(range.index, range.length, 'user');
    const before = quill.getLength();
    quill.clipboard.dangerouslyPasteHTML(range.index, extractBody(text), 'user');
    quill.setSelection(range.index + (quill.getLength() - before), 0, 'silent');  // imleç eklenenin sonuna
  }, true);

  // ---------------------------------------------------------------- görsel (medya kütüphanesi)

  async function insertImage() {
    const range = quill.getSelection(true) ?? { index: quill.getLength(), length: 0 };
    const media = await openMediaPicker({ accept: 'image', title: 'İçeriğe görsel ekle' });
    if (!media) return quill.focus();

    if (range.length) quill.deleteText(range.index, range.length, 'user');
    quill.insertEmbed(range.index, 'image', media.url, 'user');
    if (media.alt || media.title) quill.formatText(range.index, 1, 'alt', media.alt || media.title, 'user');
    quill.setSelection(range.index + 1, 0, 'silent');
  }

  // ---------------------------------------------------------------- görsel araç çubuğu
  // Görsele tıklayınca: boyut, hizalama, alt metin, değiştir (kütüphaneden), düzenle (kırp/döndür), sil

  const imageTools = createImageTools();

  function createImageTools() {
    const frame = document.createElement('div');
    frame.className = 'rich-image-frame';
    frame.hidden = true;
    const bar = document.createElement('div');
    bar.className = 'rich-image-bar';
    bar.hidden = true;
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Görsel');
    const svg = (paths) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
    // Sabit metinler (kullanıcı verisi içermez)
    bar.innerHTML = `
      <div class="grp">${Object.entries(IMAGE_SIZES).map(([cls, label]) => `<button type="button" data-size="${cls}" title="Boyut: ${label}">${label}</button>`).join('')}</div>
      <div class="grp">${Object.entries(IMAGE_ALIGNS).map(([cls, label]) => `<button type="button" data-align="${cls}" title="${label}" aria-label="${label}">${svg(ALIGN_ICONS[cls])}</button>`).join('')}</div>
      <div class="grp"><input type="text" data-alt placeholder="Alt metin (ne görünüyor?)" maxlength="200" aria-label="Alt metin"></div>
      <div class="grp">
        <button type="button" data-action="replace" title="Kütüphaneden başka görsel seç">${svg('<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>')}Değiştir</button>
        <button type="button" data-action="edit" title="Kırp, döndür">${svg('<path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/>')}Düzenle</button>
        <button type="button" data-action="delete" class="danger" title="Görseli sil" aria-label="Görseli sil">${svg('<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>')}</button>
      </div>`;
    wrapper.append(frame, bar);

    const alt = bar.querySelector('[data-alt]');
    let current = null;

    const indexOf = (img) => quill.getIndex(Quill.find(img));
    const classes = (img) => (img.getAttribute('class') ?? '').split(/\s+/).filter(Boolean);
    const pick = (list, options) => list.find((c) => c in options && c !== '') ?? '';

    function show(img) {
      current = img;
      alt.value = img.getAttribute('alt') ?? '';
      refresh();
      frame.hidden = bar.hidden = false;
      position();
    }
    function hide() {
      current = null;
      frame.hidden = bar.hidden = true;
    }
    function refresh() {
      if (!current) return;
      const list = classes(current);
      bar.querySelectorAll('[data-size]').forEach((b) => b.classList.toggle('is-on', b.dataset.size === pick(list, IMAGE_SIZES)));
      bar.querySelectorAll('[data-align]').forEach((b) => b.classList.toggle('is-on', b.dataset.align === pick(list, IMAGE_ALIGNS)));
    }
    function position() {
      if (!current) return;
      if (!quill.root.contains(current)) return hide();
      const box = wrapper.getBoundingClientRect();
      const r = current.getBoundingClientRect();
      Object.assign(frame.style, { left: `${r.left - box.left}px`, top: `${r.top - box.top}px`, width: `${r.width}px`, height: `${r.height}px` });
      const barHeight = bar.offsetHeight || 40;
      const above = r.top - box.top - barHeight - 8;
      bar.style.top = `${above > 40 ? above : r.bottom - box.top + 8}px`;
      bar.style.left = `${Math.max(8, Math.min(r.left - box.left, box.width - bar.offsetWidth - 8))}px`;
    }
    function setClass(size, align) {
      const value = [size, align].filter(Boolean).join(' ');
      quill.formatText(indexOf(current), 1, 'class', value || false, 'user');
      refresh();
      requestAnimationFrame(position);
    }
    // Görseli aynı yerde yenisiyle değiştirir (alt metin ve sınıflar korunur)
    function swap(url, newAlt) {
      const index = indexOf(current);
      const keep = { class: current.getAttribute('class') || false, alt: newAlt ?? (current.getAttribute('alt') || false) };
      quill.deleteText(index, 1, 'user');
      quill.insertEmbed(index, 'image', url, 'user');
      quill.formatText(index, 1, keep, 'user');
      const [leaf] = quill.getLeaf(index + 1);
      leaf?.domNode?.tagName === 'IMG' ? show(leaf.domNode) : hide();
    }
    // Düzenleyicideki görselin medya kaydı (/media/media/2026/11/x.webp -> media/2026/11/x.webp)
    async function mediaOf(img) {
      const path = new URL(img.getAttribute('src'), location.href).pathname.replace(/^.*?\/media\//, '');
      const { apiClient } = await import('./app.js');
      return (await apiClient('admin/api/media').get('/by-path', { path })).data;
    }

    quill.root.addEventListener('click', (e) => {
      if (e.target.tagName === 'IMG') show(e.target);
      else hide();
    });
    document.addEventListener('mousedown', (e) => {
      if (current && !bar.contains(e.target) && e.target !== current) hide();
    });
    quill.root.addEventListener('keydown', (e) => {
      if (!current) return;
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); quill.deleteText(indexOf(current), 1, 'user'); hide(); }
      else if (e.key === 'Escape') hide();
      else hide();
    });
    quill.on('text-change', () => requestAnimationFrame(position));
    quill.root.addEventListener('scroll', position);
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);

    bar.addEventListener('mousedown', (e) => { if (e.target !== alt) e.preventDefault(); });   // editörün odağı kaybolmasın
    bar.addEventListener('click', async (e) => {
      const button = e.target.closest('button');
      if (!button || !current) return;
      const list = classes(current);
      if ('size' in button.dataset) return setClass(button.dataset.size, pick(list, IMAGE_ALIGNS));
      if ('align' in button.dataset) return setClass(pick(list, IMAGE_SIZES), button.dataset.align);

      const action = button.dataset.action;
      if (action === 'delete') {
        quill.deleteText(indexOf(current), 1, 'user');
        return hide();
      }
      if (action === 'replace') {
        const img = current;
        const media = await openMediaPicker({ accept: 'image', title: 'Görseli değiştir' });
        if (media && quill.root.contains(img)) { current = img; swap(media.url, media.alt || media.title || null); }
        return;
      }
      if (action === 'edit') {
        const img = current;
        try {
          const media = await mediaOf(img);
          if (!media.editable) throw new Error('Bu görsel türü düzenlenemiyor.');
          const { openImageEditor } = await import('./media/image-editor.js');
          const result = await openImageEditor(media);
          if (!result || !quill.root.contains(img)) return;
          current = img;
          // Kopya: yeni dosya; yerine kaydet: aynı adres, düzenleyicide yenisi görünsün diye önbellek eki
          swap(result.mode === 'replace' ? `${result.media.url}?v=${Date.now()}` : result.media.url);
        } catch (err) {
          const { toast } = await import('./toast.js');
          toast(err.message ?? 'Görsel açılamadı', 'error');
        }
      }
    });
    alt.addEventListener('input', () => {
      if (current) quill.formatText(indexOf(current), 1, 'alt', alt.value.trim() || false, 'user');
    });
    alt.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === 'Escape') { e.preventDefault(); hide(); quill.focus(); } });

    return { hide };
  }

  // ---------------------------------------------------------------- HTML kaynağı

  function toggleSource() {
    imageTools.hide();
    if (!sourceMode) {
      source ??= createSourceArea();
      source.value = formatHtml(quillHtml());
      container.hidden = true;
      source.hidden = false;
      wrapper.classList.add('is-source');
      sourceMode = true;
      source.focus();
    } else {
      setHtml(source.value);
      source.hidden = true;
      container.hidden = false;
      wrapper.classList.remove('is-source');
      sourceMode = false;
      quill.focus();
      onChange?.(getHtml());
    }
    toolbar.querySelector('.ql-html').classList.toggle('ql-active', sourceMode);
  }

  function createSourceArea() {
    const ta = document.createElement('textarea');
    ta.className = 'rich-source';
    ta.spellcheck = false;
    ta.setAttribute('aria-label', 'HTML kaynağı');
    ta.hidden = true;
    ta.addEventListener('input', () => onChange?.(getHtml()));
    container.after(ta);
    return ta;
  }

  return {
    quill,
    getHtml,
    setHtml,
    enable: (enabled = true) => {
      quill.enable(enabled);
      if (source) source.disabled = !enabled;
    },
    focus: () => (sourceMode ? source.focus() : quill.focus()),
  };
}

// ------------------------------------------------------------------ yardımcılar

// Eski düz metin yazılar: boş satırlar paragraf, tek satır sonları <br>
function toHtml(value) {
  const text = String(value ?? '');
  if (/^\s*<(p|h[1-6]|ul|ol|blockquote|pre|div|hr|table)[\s>/]/i.test(text)) return text;   // Html::isHtml ile aynı kural
  return text
    .split(/\n{2,}/)
    .filter((p) => p.trim() !== '')
    .map((p) => `<p>${escapeHtml(p).replace(/\n/g, '<br>')}</p>`)
    .join('');
}

// Tam bir HTML belgesi yapıştırılırsa sadece <body> içeriği alınır
function extractBody(html) {
  const match = html.match(/<body[^>]*>([\s\S]*)<\/body>/i);
  return match ? match[1] : html;
}

// Kaynak görünümünde okunaklı olsun diye blok etiketlerinden sonra satır sonu
function formatHtml(html) {
  return html.replace(/(<\/(p|h[1-6]|li|blockquote|pre|ul|ol)>)(?!\n)/gi, '$1\n').replace(/(<(ul|ol)>)(?!\n)/gi, '$1\n').trim();
}

function escapeHtml(s) {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
