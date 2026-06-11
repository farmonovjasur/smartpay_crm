import { createContext } from 'react';

/** @typedef {'uz'|'ru'} Locale */

export const STORAGE_KEY = 'smartpay_locale';
export const DEFAULT_LOCALE = 'uz';

/**
 * LocalStorage'dan saqlangan tilni o'qiydi.
 * @returns {Locale}
 */
export function getStoredLocale() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'uz' || stored === 'ru') return stored;
  } catch {
    // localStorage mavjud emas
  }
  return DEFAULT_LOCALE;
}

/** @type {import('react').Context<{locale: Locale, setLocale: (l: Locale) => void}>} */
export const I18nContext = createContext({ locale: DEFAULT_LOCALE, setLocale: () => {} });
