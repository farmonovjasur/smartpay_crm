import { uz } from './i18n/uz';
import { ru } from './i18n/ru';

const LOCALES = { uz, ru };
const STORAGE_KEY = 'smartpay_locale';
const MAX_SIZE = 5_242_880; // 5 MB

function getMessages() {
  let locale = 'uz';
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'uz' || stored === 'ru') locale = stored;
  } catch { /* ignore */ }
  return LOCALES[locale]?.validation || LOCALES.uz.validation;
}

/**
 * Import fayl validatsiyasi: faqat .xlsx va ≤ 5MB.
 * @param {{filename: string, size: number}} file
 * @returns {{valid: boolean, error?: string}}
 */
export function validateImportFile({ filename, size }) {
  const msg = getMessages();
  if (!filename || !filename.toLowerCase().endsWith('.xlsx')) {
    return { valid: false, error: msg.fileXlsxOnly };
  }
  if (size > MAX_SIZE) {
    return { valid: false, error: msg.fileTooLarge };
  }
  return { valid: true };
}
