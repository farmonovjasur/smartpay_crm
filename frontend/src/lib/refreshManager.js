import axios from 'axios';
import { store } from '@/store';
import { clearAuth } from '@/store/authSlice';

let refreshPromise = null;

/**
 * Single-flight refresh — faqat bitta so'rov yuboradi, boshqalar kutadi.
 * 401 → clearAuth (status='guest'); router guard'lari login'ga client-side yo'naltiradi.
 * 5xx/tarmoq → sessiyani saqlaydi, reject.
 *
 * MUHIM: bu yerda window.location bilan HARD RELOAD QILMAYMIZ —
 * aks holda bootstrap (/auth/me) qayta ishga tushib cheksiz reload tsikli yuzaga keladi.
 * @returns {Promise<void>}
 */
export function runRefresh() {
  if (refreshPromise) return refreshPromise;

  refreshPromise = axios
    .post(`${import.meta.env.VITE_API_BASE_URL}/auth/refresh`, null, {
      withCredentials: true,
      timeout: 10000,
    })
    .then(() => {})
    .catch((err) => {
      if (err.response?.status === 401) {
        // Sessiya yo'q yoki tugagan — holatni tozalaymiz (reload yo'q).
        if (store.getState().auth.status !== 'guest') {
          store.dispatch(clearAuth());
        }
      }
      throw err;
    })
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
}
