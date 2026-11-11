/**
 * Koleksiyon listesi: satırlar sürükle-bırak ile sıralanır (SortableJS), sıra hemen kaydedilir.
 * Sunucu: app/Controllers/Admin/CollectionController.php (sort)
 */
import { createHttp } from '../lib/http.js';
import { config } from '../lib/app.js';
import { toast } from '../lib/toast.js';

const list = document.querySelector('[data-collection-list]');
if (list) {
  const http = createHttp({ baseUrl: '', csrf: config.csrf });
  import('https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/+esm').then(({ default: Sortable }) => {
    Sortable.create(list, {
      handle: '[data-handle]',
      animation: 150,
      onEnd: async () => {
        try {
          await http.post(list.dataset.sortUrl, { ids: [...list.children].map((row) => Number(row.dataset.id)) });
          toast('Sıra kaydedildi.');
        } catch (err) {
          toast(err.message, 'error');
        }
      },
    });
  });
}
