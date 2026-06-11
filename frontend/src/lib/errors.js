import { uz } from './i18n/uz';
import { ru } from './i18n/ru';

const LOCALES = { uz, ru };
const STORAGE_KEY = 'smartpay_locale';

/**
 * Joriy tilni localStorage'dan o'qiydi (hook tashqarisida ishlatiladi).
 * @returns {'uz'|'ru'}
 */
function getCurrentLocale() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'uz' || stored === 'ru') return stored;
  } catch {
    // ignore
  }
  return 'uz';
}

/**
 * HTTP xato kodini xabarni joriy tilda qaytaradi. Total funksiya — har doim bo'sh bo'lmagan string.
 * @param {{status: number|null, body?: any, isNetwork?: boolean}} params
 * @returns {string}
 */
export function mapErrorToMessage({ status, body, isNetwork }) {
  const locale = getCurrentLocale();
  const e = LOCALES[locale]?.errors || LOCALES.uz.errors;

  if (isNetwork) return e.network;
  switch (status) {
    case 400: return body?.message || e.badRequest;
    case 401: return e.unauthorized;
    case 403: return e.forbidden;
    case 409: return body?.message || e.conflict;
    case 413: return e.payloadTooLarge;
    case 422: return body?.message || e.validationError;
    case 429: return e.tooManyRequests;
    case 500: return e.serverError;
    case 503: return e.serviceUnavailable;
    default: return e.unknown;
  }
}
