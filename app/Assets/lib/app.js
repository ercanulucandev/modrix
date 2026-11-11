/**
 * Admin sayfalarının ortak ayarları (admin layout'undaki <meta> etiketlerinden) ve hazır API istemcisi.
 *
 *   import { apiClient, url } from '@app/lib/app.js';
 *   const http = apiClient('admin/api/media');      // CSRF + oturum düşünce girişe yönlendirme hazır
 *   url('media/x.png')                              // site alt klasördeyse de doğru adres
 */
import { createHttp } from './http.js';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? '';

export const config = {
  base: meta('app-base').replace(/\/$/, ''),
  csrf: meta('csrf-token'),
  loginUrl: meta('app-login'),
};

export function url(path = '') {
  return `${config.base}/${String(path).replace(/^\//, '')}`;
}

export function apiClient(path) {
  return createHttp({
    baseUrl: url(path),
    csrf: config.csrf,
    onUnauthorized: () => window.location.assign(config.loginUrl || url('admin/login')),
  });
}
