/**
 * SmartPay CRM — yengil i18n (Internationalization) tizimi.
 *
 * Faqat ikki til qo'llab-quvvatlanadi: 'uz' va 'ru'.
 * Hech qanday tashqi kutubxona talab qilinmaydi.
 *
 * Foydalanish:
 *   import { useT } from '@/lib/i18n';
 *   const t = useT();
 *   t('nav.dashboard') → "Boshqaruv paneli" (UZ) yoki "Панель управления" (RU)
 *
 * Interpolatsiya:
 *   t('auth.rateLimited', { seconds: 45 }) → "Urinishlar soni oshib ketdi, 45 soniyadan keyin qayta urining"
 */

import { useContext, useCallback } from 'react';
import { I18nContext, DEFAULT_LOCALE } from './context';
import { uz } from './uz';
import { ru } from './ru';

// Re-export Provider (JSX fayldan)
export { I18nProvider } from './Provider';

const LOCALES = { uz, ru };

/**
 * @param {string} key — nuqtali kalit, masalan "nav.dashboard"
 * @param {Record<string, any>} messages — barcha tarjimalar
 * @returns {string|undefined}
 */
function resolve(key, messages) {
  const parts = key.split('.');
  let current = messages;
  for (const part of parts) {
    if (current == null || typeof current !== 'object') return undefined;
    current = current[part];
  }
  return typeof current === 'string' ? current : undefined;
}

/**
 * Interpolatsiya: `{key}` → qiymat.
 * @param {string} template
 * @param {Record<string, any>} [params]
 * @returns {string}
 */
function interpolate(template, params) {
  if (!params) return template;
  return template.replace(/\{(\w+)\}/g, (_, k) => (params[k] != null ? String(params[k]) : `{${k}}`));
}

/**
 * Joriy tilni va almashtirgichni qaytaradi.
 * @returns {{ locale: 'uz'|'ru', setLocale: (l: 'uz'|'ru') => void, isUz: boolean, isRu: boolean }}
 */
export function useLocale() {
  const { locale, setLocale } = useContext(I18nContext);
  return { locale, setLocale, isUz: locale === 'uz', isRu: locale === 'ru' };
}

/**
 * Tarjima funksiyasini qaytaradi.
 * @returns {(key: string, params?: Record<string, any>) => string}
 */
export function useT() {
  const { locale } = useContext(I18nContext);
  const messages = LOCALES[locale] || LOCALES[DEFAULT_LOCALE];
  const fallback = LOCALES[DEFAULT_LOCALE];

  return useCallback(
    (key, params) => {
      const val = resolve(key, messages) ?? resolve(key, fallback) ?? key;
      return interpolate(val, params);
    },
    [messages, fallback]
  );
}

export { uz, ru };
