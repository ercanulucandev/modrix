/**
 * Köşede kısa bildirim.
 *   toast('Kaydedildi');            toast('Hata oluştu', 'error');
 */
import { h, icon } from './dom.js';

let region;

function getRegion() {
  if (region && document.body.contains(region)) return region;
  region = h('div', {
    class: 'pointer-events-none fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2',
    'aria-live': 'polite',
  });
  document.body.append(region);
  return region;
}

export function toast(message, type = 'success', duration = 3500) {
  const isError = type === 'error';
  const el = h('div', {
    class: 'pointer-events-auto flex items-start gap-3 rounded-lg border bg-background p-4 text-sm shadow-lg transition-all duration-200 translate-y-2 opacity-0',
    role: isError ? 'alert' : 'status',
  }, [
    icon(isError ? 'alert' : 'check', `mt-0.5 size-4 shrink-0 ${isError ? 'text-destructive' : 'text-emerald-600'}`),
    h('p', { class: 'flex-1' }, message),
  ]);

  getRegion().append(el);
  requestAnimationFrame(() => el.classList.remove('translate-y-2', 'opacity-0'));

  setTimeout(() => {
    el.classList.add('opacity-0');
    setTimeout(() => el.remove(), 200);
  }, duration);
}
