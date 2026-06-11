import axios from 'axios';
import { store } from '@/store';
import { clearAuth } from '@/store/authSlice';
import { readCsrfToken } from './csrf';
import { runRefresh } from './refreshManager';
import { normalizeError } from './serverErrors';

const baseURL = import.meta.env.VITE_API_BASE_URL;

// R16.4 — production'da API HTTPS orqali ishlashi shart (cookie `Secure` talabi).
// Faqat absolyut URL'ni tekshiramiz; nisbiy `/api` (Vite proksisi) ham OK.
if (
  import.meta.env.PROD &&
  typeof baseURL === 'string' &&
  /^https?:\/\//i.test(baseURL) &&
  !baseURL.toLowerCase().startsWith('https://')
) {
  // eslint-disable-next-line no-console
  console.warn(
    '[smartpay] VITE_API_BASE_URL HTTPS emas — production cookie\'lar ishlamasligi mumkin:',
    baseURL
  );
}

const api = axios.create({
  baseURL,
  withCredentials: true,
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' },
});

const AUTH_URLS = ['/auth/login', '/auth/refresh'];
const STATE_METHODS = ['post', 'put', 'patch', 'delete'];

// Request interceptor — CSRF (state-changing so'rovlarda)
api.interceptors.request.use((config) => {
  const method = config.method?.toLowerCase();
  const isAuthUrl = AUTH_URLS.some((u) => config.url?.includes(u));

  if (STATE_METHODS.includes(method) && !isAuthUrl) {
    const csrf = readCsrfToken();
    if (csrf) config.headers['X-CSRF-Token'] = csrf;
  }

  return config;
});

// Response interceptor — 401 refresh + error normalization
api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const originalRequest = error.config;
    const isAuthUrl = AUTH_URLS.some((u) => originalRequest?.url?.includes(u));

    if (error.response?.status === 401 && !isAuthUrl && !originalRequest._retried && !originalRequest.skipAuthRefresh) {
      originalRequest._retried = true;
      try {
        await runRefresh();
        return api(originalRequest);
      } catch {
        // runRefresh handles clearAuth on 401
      }
    }

    error.normalized = normalizeError(error);
    return Promise.reject(error);
  }
);

export default api;
