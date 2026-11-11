/**
 * Küçük HTTP istemcisi.
 *
 *   const http = createHttp({ baseUrl: '/admin/api/blog', csrf: 'token' });
 *   const { data, meta } = await http.get('/posts', { q: 'php', page: 2 });
 *   await http.post('/posts', { title: 'Merhaba' });
 *   await http.put('/posts/5', { title: 'Yeni' });
 *   await http.delete('/posts/5');
 *
 * Her yanıt JSON'dur. Başarısız yanıtlar ApiError fırlatır:
 *   err.status   -> 422, 404, 401, 419, 500...
 *   err.message  -> sunucunun "error" mesajı
 *   err.errors   -> { alan: 'mesaj' } (sadece 422 doğrulama hatalarında)
 */

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }

  get isValidation() { return this.status === 422; }
  get isUnauthorized() { return this.status === 401; }
  get isCsrf() { return this.status === 419; }
}

export function createHttp({ baseUrl, csrf, onUnauthorized } = {}) {
  async function request(method, path, { body, query } = {}) {
    const url = new URL(baseUrl.replace(/\/$/, '') + path, window.location.origin);
    Object.entries(query ?? {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
    });

    let response;
    try {
      response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
          ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch {
      throw new ApiError(0, 'Sunucuya ulaşılamadı. İnternet bağlantınızı kontrol edin.');
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      const error = new ApiError(
        response.status,
        payload.error ?? `İstek başarısız (${response.status})`,
        payload.errors ?? {},
      );
      if (error.isUnauthorized && onUnauthorized) onUnauthorized(error);
      throw error;
    }
    return payload;
  }

  return {
    get: (path, query) => request('GET', path, { query }),
    post: (path, body) => request('POST', path, { body }),
    put: (path, body) => request('PUT', path, { body }),
    delete: (path) => request('DELETE', path),
  };
}
