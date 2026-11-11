/**
 * Sunucudan gelen şemadan form oluşturur ve modeli yönetir.
 *
 *   const form = createForm(schema.fields, post);   // post: düzenlenen kayıt (yeni kayıtta null)
 *   modalContent.append(form.element);
 *   await form.ready                -> HTML editör gibi geç yüklenen alanlar hazır
 *   form.values()                   -> { title: '...', status: 'published', body: '<p>…</p>' }
 *   form.setErrors(err.errors)      -> alanların altına sunucu hatalarını yazar
 *   form.clearErrors()
 *   form.isDirty()                  -> kullanıcı bir şey değiştirdi mi
 *   form.setDisabled(true)
 *   form.onSubmit(handler)          -> Enter / Ctrl+Enter ile gönderim
 *
 * Şema alanı: { name, label, type, required, maxLength, placeholder, help, prefix, rows, default, autofocus, options: [{value, label}] }
 * Türler: text, email, url, number, date, textarea, select, richtext (HTML editör)
 *
 * Her alan aynı arayüze sahiptir (bkz. field()):  { element, value(), focus(), setInvalid(bool), setDisabled(bool), ready }
 */
import { h } from './dom.js';
import { createEditor } from './editor.js';

export function createForm(fields, model = null) {
  const items = new Map();   // name -> { field, control, error }
  let submitHandler = null;

  const form = h('form', {
    class: 'space-y-5',
    novalidate: true,
    onSubmit: (e) => {
      e.preventDefault();
      submitHandler?.();
    },
  });

  for (const field of fields) {
    const value = model?.[field.name] ?? field.default ?? '';
    const control = field.type === 'richtext' ? richTextControl(field, value) : inputControl(field, value);
    const error = h('p', { class: 'field-error hidden', id: `err-${field.name}`, role: 'alert' });

    control.onInput(() => setFieldError(field.name, null));
    items.set(field.name, { field, control, error });

    form.append(h('div', { class: 'space-y-2' }, [
      h('div', { class: 'flex items-center justify-between gap-2' }, [
        h('label', { class: 'label', for: `f-${field.name}` }, [
          field.label,
          field.required ? h('span', { class: 'text-destructive' }, ' *') : null,
        ]),
        control.counter ?? null,
      ]),
      control.element,
      error,
      field.help ? h('p', { class: 'field-help' }, field.help) : null,
    ]));
  }

  // Tek satırlık alanlarda Enter formu göndersin (görünmez gönder butonu)
  form.append(h('button', { type: 'submit', class: 'hidden', tabindex: '-1', 'aria-hidden': 'true' }));

  // Ctrl/Cmd + Enter her alandan gönderir (editör ve textarea dahil)
  form.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      submitHandler?.();
    }
  });

  // Geç yüklenen alanlar (editör) hazır olunca "ilk değer" o andaki değer olur;
  // böylece editörün HTML'i normalleştirmesi "değişiklik" sayılmaz.
  const ready = Promise.all([...items.values()].map((i) => i.control.ready));
  const initial = {};
  const snapshot = () => { for (const [name, i] of items) initial[name] = i.control.value(); };
  snapshot();
  ready.then(snapshot);

  function setFieldError(name, message) {
    const item = items.get(name);
    if (!item) return;
    item.error.textContent = message ?? '';
    item.error.classList.toggle('hidden', !message);
    item.control.setInvalid(Boolean(message), item.error.id);
  }

  return {
    element: form,
    ready,

    values() {
      const out = {};
      for (const [name, i] of items) out[name] = i.control.value();
      return out;
    },

    setErrors(errors = {}) {
      let first = null;
      for (const [name, message] of Object.entries(errors)) {
        setFieldError(name, message);
        first ??= items.get(name)?.control;
      }
      first?.focus();
    },

    clearErrors() {
      for (const name of items.keys()) setFieldError(name, null);
    },

    isDirty() {
      for (const [name, i] of items) if (i.control.value() !== initial[name]) return true;
      return false;
    },

    setDisabled(disabled) {
      for (const i of items.values()) i.control.setDisabled(disabled);
    },

    onSubmit(handler) { submitHandler = handler; },
  };
}

// ------------------------------------------------------------------ alan türleri

// Standart input / textarea / select
function inputControl(field, value) {
  const common = {
    id: `f-${field.name}`,
    name: field.name,
    class: 'input mt-0',
    required: field.required,
    placeholder: field.placeholder,
    autofocus: field.autofocus,
    maxlength: field.maxLength,
  };

  let el;
  if (field.type === 'textarea') {
    el = h('textarea', { ...common, rows: field.rows ?? 5, class: 'input mt-0 resize-y leading-6' });
  } else if (field.type === 'select') {
    el = h('select', { ...common, placeholder: null, maxlength: null },
      (field.options ?? []).map((o) => h('option', { value: o.value }, o.label)));
  } else {
    el = h('input', { ...common, type: field.type ?? 'text' });
  }
  el.value = value;

  // Karakter sayacı (maxLength'i olan metin alanları)
  let counter = null;
  if (field.maxLength && ['text', 'textarea'].includes(field.type)) {
    counter = h('span', { class: 'text-xs tabular-nums text-muted-foreground' });
    const update = () => { counter.textContent = `${el.value.length}/${field.maxLength}`; };
    el.addEventListener('input', update);
    update();
  }

  return {
    element: field.prefix ? withPrefix(field.prefix, el) : el,
    counter,
    ready: Promise.resolve(),
    value: () => el.value.trim(),
    focus: () => el.focus(),
    onInput: (fn) => el.addEventListener('input', fn),
    setDisabled: (d) => { el.disabled = d; },
    setInvalid: (invalid, describedBy) => {
      el.classList.toggle('input-error', invalid);
      el.toggleAttribute('aria-invalid', invalid);
      if (invalid) el.setAttribute('aria-describedby', describedBy);
    },
  };
}

// HTML editör: yüklenene kadar "yükleniyor", yüklenemezse düz textarea'ya düşer
function richTextControl(field, value) {
  let html = String(value ?? '');
  const listeners = [];
  const changed = (next) => { html = next; listeners.forEach((fn) => fn()); };

  const host = h('div');
  const loader = h('div', { class: 'flex h-72 items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground' }, 'Editör yükleniyor…');
  const wrapper = h('div', { class: 'rich-editor', id: `f-${field.name}` }, [loader, host]);

  let editor = null;
  let fallback = null;

  const ready = createEditor(host, { value: html, placeholder: field.placeholder, onChange: changed })
    .then((ed) => {
      editor = ed;
      html = ed.getHtml();
      loader.remove();
    })
    .catch(() => {
      // CDN'e ulaşılamazsa içerik kaybolmasın: düz HTML alanı
      loader.remove();
      host.remove();
      fallback = h('textarea', { class: 'input mt-0 min-h-72 font-mono text-xs', placeholder: 'Editör yüklenemedi; HTML olarak düzenleyebilirsiniz.' });
      fallback.value = html;
      fallback.addEventListener('input', () => changed(fallback.value));
      wrapper.append(fallback);
    });

  return {
    element: wrapper,
    ready,
    value: () => (fallback ? fallback.value.trim() : html),
    focus: () => (editor ? editor.focus() : fallback?.focus()),
    onInput: (fn) => listeners.push(fn),
    setDisabled: (d) => { editor?.enable(!d); if (fallback) fallback.disabled = d; },
    setInvalid: (invalid) => wrapper.classList.toggle('is-invalid', invalid),
  };
}

// "/blog/" gibi sabit ön ek + input
function withPrefix(prefix, control) {
  control.classList.add('rounded-l-none');
  return h('div', { class: 'flex' }, [
    h('span', { class: 'inline-flex items-center rounded-l-md border border-r-0 border-input bg-muted px-3 font-mono text-xs text-muted-foreground' }, prefix),
    control,
  ]);
}
