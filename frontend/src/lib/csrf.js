/**
 * Backend qo'ygan `csrf_token` (non-HttpOnly) cookie qiymatini o'qiydi.
 * Bu qiymat har bir state-changing so'rovda `X-CSRF-Token` header sifatida qaytariladi
 * (CSRF Double-Submit pattern).
 * @returns {string|null}
 */
export function readCsrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}
