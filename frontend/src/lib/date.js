/**
 * Sanani DD.MM.YYYY formatda qaytaradi (nol bilan to'ldirilgan).
 * @param {string|Date} value — ISO string yoki Date
 * @returns {string} — masalan "05.01.2026"
 */
export function formatDate(value) {
  if (!value) return '';
  const d = value instanceof Date ? value : new Date(value);
  if (isNaN(d.getTime())) return '';
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  return `${day}.${month}.${year}`;
}

const MONTHS_UZ = [
  'Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun',
  'Iyul', 'Avgust', 'Sentyabr', 'Oktyabr', 'Noyabr', 'Dekabr',
];

/**
 * `YYYY-MM` davrini o'zbekcha "Oy YYYY" formatiga aylantiradi.
 * @param {string} period — masalan "2026-05"
 * @returns {string} — masalan "May 2026". Noto'g'ri qiymat uchun original string qaytadi.
 */
export function formatPeriod(period) {
  if (typeof period !== 'string') return '';
  const m = /^(\d{4})-(0[1-9]|1[0-2])$/.exec(period);
  if (!m) return period;
  const year = m[1];
  const monthIdx = Number(m[2]) - 1;
  return `${MONTHS_UZ[monthIdx]} ${year}`;
}
