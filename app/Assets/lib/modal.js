/**
 * Modal (dialog). Esc ve dış alana tıklama kapatır, odak modal içinde kalır.
 *
 *   const modal = openModal({
 *     title: 'Yeni yazı',
 *     description: 'Opsiyonel alt başlık',
 *     size: 'lg',                       // sm | md | lg | xl
 *     content: formElement,             // Node
 *     footer: [cancelButton, saveButton],
 *     onClose: () => {},
 *     beforeClose: () => true,          // false dönerse kapanmaz (ör. kaydedilmemiş değişiklik)
 *   });
 *   modal.close();                      modal.close({ force: true });
 *   modal.setBusy(true);                // kapatmayı geçici engeller (kaydederken)
 *
 *   const ok = await confirmDialog({ title: 'Silinsin mi?', message: '...', confirmText: 'Sil', danger: true });
 */
import { h, icon } from './dom.js';

const SIZES = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-6xl' };

// Açık modallar (iç içe açılabilir: yazı modalı -> medya seçici). Klavyeye sadece en üstteki tepki verir.
const stack = [];

export function openModal({ title, description, size = 'md', content, footer = [], onClose, beforeClose } = {}) {
  const previousFocus = document.activeElement;
  const id = `modal-title-${Math.random().toString(36).slice(2, 8)}`;
  let busy = false;
  let closed = false;

  // force: beforeClose sorulmadan kapat (ör. başarılı kayıttan sonra)
  const close = ({ force = false } = {}) => {
    if (busy || closed) return;
    if (!force && beforeClose && beforeClose() === false) return;
    closed = true;
    overlay.classList.add('opacity-0');
    panel.classList.add('scale-95', 'opacity-0');
    document.removeEventListener('keydown', onKeydown);
    stack.splice(stack.indexOf(onKeydown), 1);
    setTimeout(() => {
      overlay.remove();
      if (!stack.length) document.body.style.overflow = '';
      previousFocus?.focus?.();
      onClose?.();
    }, 150);
  };

  const onKeydown = (e) => {
    if (stack[stack.length - 1] !== onKeydown) return;   // üstte başka modal var
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
    }
    if (e.key === 'Tab') trapFocus(e, panel);
  };

  const panel = h('div', {
    class: `relative flex max-h-[calc(100vh-2rem)] w-full ${SIZES[size] ?? SIZES.md} scale-95 flex-col rounded-lg border bg-background opacity-0 shadow-lg transition-all duration-150`,
    role: 'dialog',
    'aria-modal': 'true',
    'aria-labelledby': id,
  }, [
    h('div', { class: 'flex items-start justify-between gap-4 border-b px-6 py-4' }, [
      h('div', { class: 'space-y-1' }, [
        h('h2', { id, class: 'text-lg font-semibold leading-none tracking-tight' }, title),
        description ? h('p', { class: 'text-sm text-muted-foreground' }, description) : null,
      ]),
      h('button', {
        type: 'button', class: 'btn btn-ghost size-8 px-0 -mr-2 -mt-1', 'aria-label': 'Kapat', onClick: () => close(),
      }, icon('x')),
    ]),
    h('div', { class: 'flex-1 overflow-y-auto px-6 py-5' }, content),
    footer.length ? h('div', { class: 'flex flex-col-reverse gap-2 border-t px-6 py-4 sm:flex-row sm:justify-end' }, footer) : null,
  ]);

  const overlay = h('div', {
    class: 'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 opacity-0 transition-opacity duration-150',
    onMousedown: (e) => { if (e.target === overlay) close(); },
  }, panel);

  document.body.append(overlay);
  document.body.style.overflow = 'hidden';
  document.addEventListener('keydown', onKeydown);
  stack.push(onKeydown);

  requestAnimationFrame(() => {
    overlay.classList.remove('opacity-0');
    panel.classList.remove('scale-95', 'opacity-0');
    (panel.querySelector('[autofocus]') ?? panel.querySelector('input, textarea, select, button'))?.focus();
  });

  return {
    element: panel,
    close,
    setBusy(value) { busy = value; },
  };
}

export function confirmDialog({ title, message, confirmText = 'Onayla', cancelText = 'Vazgeç', danger = false }) {
  return new Promise((resolve) => {
    let result = false;
    const confirmBtn = h('button', {
      type: 'button',
      class: `btn ${danger ? 'btn-danger' : 'btn-primary'}`,
      onClick: () => { result = true; modal.close(); },
    }, confirmText);
    const cancelBtn = h('button', { type: 'button', class: 'btn btn-secondary', onClick: () => modal.close() }, cancelText);

    const modal = openModal({
      title,
      size: 'sm',
      content: h('p', { class: 'text-sm text-muted-foreground' }, message),
      footer: [cancelBtn, confirmBtn],
      onClose: () => resolve(result),
    });
    confirmBtn.focus();
  });
}

function trapFocus(e, container) {
  const focusable = [...container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')];
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (e.shiftKey && document.activeElement === first) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
}
