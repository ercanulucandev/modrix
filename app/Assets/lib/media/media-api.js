/**
 * Medya kütüphanesi API'si. Sunucu: app/Controllers/Admin/MediaApiController.php
 *
 *   mediaApi.list({ q, type, page, per_page })   -> { data: [media], meta: { total, page, pages, limits } }
 *   mediaApi.upload(file, (oran) => ...)         -> { data: [media], errors, message }   (ilerleme 0..1)
 *   mediaApi.update(id, { title, alt })          -> { data, message }
 *   mediaApi.edit(id, blob, { mode, filename })  -> { data, mode, message }   (görsel düzenleyici)
 *   mediaApi.remove(id)                          -> { message }
 *   mediaApi.removeMany([id])                    -> { done, failed, message }
 *
 * media: { id, name, title, alt, type: 'image'|'document', mime, extension, size, size_label,
 *          width, height, path, url, thumb_url, created_at }
 */
import { apiClient, config, url } from '../app.js';
import { ApiError } from '../http.js';

const http = apiClient('admin/api/media');

export const mediaApi = {
  list: ({ q, type, page, perPage } = {}) => http.get('/', { q, type, page, per_page: perPage }),
  get: (id) => http.get(`/${id}`),
  update: (id, values) => http.put(`/${id}`, values),
  remove: (id) => http.delete(`/${id}`),
  // Toplu silme: { done: [id], failed: { id: 'hata' }, message }
  removeMany: (ids) => http.post('/bulk', { action: 'delete', ids }),

  upload(file, onProgress) {
    const body = new FormData();
    body.append('files[]', file);
    return send('admin/api/media', body, onProgress);
  },

  // Görsel düzenleyici: mode 'copy' (yeni kayıt) | 'replace' (aynı adres, aynı format)
  edit(id, blob, { mode = 'copy', filename = 'gorsel.png', onProgress } = {}) {
    const body = new FormData();
    body.append('file', blob, filename);
    body.append('mode', mode);
    return send(`admin/api/media/${id}/edit`, body, onProgress);
  },
};

// fetch yükleme ilerlemesi veremediği için XHR
function send(path, body, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url(path));
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);

      xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress?.(e.loaded / e.total); };
      xhr.onerror = () => reject(new ApiError(0, 'Sunucuya ulaşılamadı.'));
      xhr.onload = () => {
        let payload = {};
        try { payload = JSON.parse(xhr.responseText); } catch { /* boş yanıt */ }

        if (xhr.status >= 200 && xhr.status < 300) return resolve(payload);
        if (xhr.status === 401) window.location.assign(config.loginUrl);
        const message = payload.messages?.[0] ?? payload.errors?.files ?? payload.error ?? `Yükleme başarısız (${xhr.status})`;
        reject(new ApiError(xhr.status, message, payload.errors ?? {}));
      };

      xhr.send(body);
    });
}
