/**
 * İçerik geçmişi penceresi (yazı/sayfa düzenleyicisi > Geçmiş).
 *   openRevisions({ type: 'post', id: 5, onRestore: (fields) => { ... } })
 * Solda sürümler, sağda seçilenin önizlemesi; "Bu sürümü yükle" alanları düzenleyiciye koyar (kaydedince geçerli olur).
 * Sunucu: app/Controllers/Admin/RevisionApiController.php
 */
import { apiClient } from './app.js';
import { h, formatDate } from './dom.js';
import { openModal } from './modal.js';
import { toast } from './toast.js';

const api = apiClient('admin/api/revisions');

export async function openRevisions({ type, id, onRestore }) {
  let list;
  try {
    list = (await api.get(`/${type}/${id}`)).data;
  } catch (err) {
    toast(err.message, 'error');
    return;
  }

  let selected = null;
  const items = h('div', { class: 'flex max-h-[60vh] flex-col gap-1 overflow-y-auto pr-1' });
  const preview = h('div', { class: 'min-h-48 rounded-md border p-4 text-sm text-muted-foreground' }, 'Soldan bir sürüm seçin.');
  const restore = h('button', { type: 'button', class: 'btn btn-primary', disabled: true, onClick: () => {
    if (!selected) return;
    onRestore(selected.fields);
    modal.close({ force: true });
    toast('Sürüm düzenleyiciye yüklendi. Geçerli olması için kaydedin.', 'info');
  } }, 'Bu sürümü yükle');

  async function select(rev, button) {
    items.querySelectorAll('button').forEach((b) => b.classList.remove('bg-accent'));
    button.classList.add('bg-accent');
    preview.textContent = 'Yükleniyor…';
    try {
      selected = (await api.get(`/${rev.id}`)).data;
      const body = h('div', { class: 'prose-content max-h-[50vh] overflow-y-auto text-foreground' });
      body.innerHTML = selected.html;   // sunucuda Html::render ile temizlenmiş
      preview.replaceChildren(h('p', { class: 'mb-3 text-base font-semibold text-foreground' }, selected.fields.title ?? ''), body);
      restore.disabled = false;
    } catch (err) {
      preview.textContent = err.message;
    }
  }

  items.append(...list.map((rev, i) => {
    const button = h('button', { type: 'button', class: 'rounded-md px-3 py-2 text-left text-sm hover:bg-accent' }, [
      h('span', { class: 'block font-medium' }, formatDate(rev.created_at) + (i === 0 ? ' · şu anki' : '')),
      h('span', { class: 'block text-xs text-muted-foreground' }, `${rev.user ?? 'Sistem'} · ${rev.words} kelime`),
    ]);
    button.addEventListener('click', () => select(rev, button));
    return button;
  }));
  if (!list.length) items.append(h('p', { class: 'px-3 py-2 text-sm text-muted-foreground' }, 'Henüz kayıtlı sürüm yok. Her kaydetmede bir sürüm eklenir.'));

  const modal = openModal({
    title: 'Geçmiş',
    description: 'Her kaydetmede bir sürüm saklanır (en fazla 30).',
    size: 'xl',
    content: h('div', { class: 'grid gap-4 md:grid-cols-[14rem_1fr]' }, [items, preview]),
    footer: [h('button', { type: 'button', class: 'btn btn-secondary', onClick: () => modal.close() }, 'Kapat'), restore],
  });
}
