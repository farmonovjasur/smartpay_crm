import { uz } from './i18n/uz';
import { ru } from './i18n/ru';

const LOCALES = { uz, ru };
const STORAGE_KEY = 'smartpay_locale';

function getValidationMessages() {
  let locale = 'uz';
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'uz' || stored === 'ru') locale = stored;
  } catch { /* ignore */ }
  return LOCALES[locale]?.validation || LOCALES.uz.validation;
}

/** @type {Record<string, (v: any) => string|null>} */
export const validators = {
  inn: (v) => {
    const msg = getValidationMessages();
    return /^\d{9}$|^\d{14}$/.test(v) ? null : msg.inn;
  },
  phone: (v) => {
    const msg = getValidationMessages();
    return /^\+998\d{9}$/.test(v) ? null : msg.phone;
  },
  product_count: (v) => {
    const msg = getValidationMessages();
    const n = Number(v);
    return Number.isInteger(n) && n >= 1 && n <= 1_000_000 ? null : msg.productCount;
  },
  service_date: (v) => {
    const msg = getValidationMessages();
    const d = new Date(v);
    if (isNaN(d.getTime())) return msg.serviceDateInvalid;
    const today = new Date();
    today.setHours(23, 59, 59, 999);
    return d <= today ? null : msg.serviceDate;
  },
  name: (v) => {
    const msg = getValidationMessages();
    return (typeof v === 'string' && v.length >= 1 && v.length <= 255) ? null : msg.name;
  },
  notes: (v) => {
    const msg = getValidationMessages();
    if (!v) return null;
    return v.length <= 1000 ? null : msg.notes;
  },
  period: (v) => {
    const msg = getValidationMessages();
    return /^\d{4}-(0[1-9]|1[0-2])$/.test(v) ? null : msg.period;
  },
  email: (v) => {
    const msg = getValidationMessages();
    return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? null : msg.email;
  },
};
