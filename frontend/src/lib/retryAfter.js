/**
 * Retry-After header qiymatini musbat butun songa aylantiradi.
 * NaN/manfiy/bo'sh bo'lsa standart 60 qaytaradi.
 * @param {string|number|null|undefined} headerValue
 * @returns {number}
 */
export function parseRetryAfter(headerValue) {
  const n = parseInt(headerValue, 10);
  return Number.isFinite(n) && n > 0 ? n : 60;
}
