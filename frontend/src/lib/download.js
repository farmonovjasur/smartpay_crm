import api from './api';

/**
 * Blob faylni yuklab oladi va brauzer orqali saqlaydi.
 * @param {string} url
 * @param {object} [params]
 * @param {string} [fallbackName='download.xlsx']
 * @returns {Promise<void>}
 */
export async function downloadFile(url, params, fallbackName = 'download.xlsx') {
  const res = await api.get(url, { params, responseType: 'blob' });
  const filename = parseFilename(res.headers['content-disposition'], fallbackName);
  const blob = new Blob([res.data]);
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}

/**
 * Content-Disposition headerdan fayl nomini ajratadi.
 * Topilmasa yoki bo'sh bo'lsa fallbackName qaytaradi.
 * @param {string|null|undefined} contentDisposition
 * @param {string} [fallbackName='download.xlsx']
 * @returns {string}
 */
export function parseFilename(contentDisposition, fallbackName = 'download.xlsx') {
  if (!contentDisposition) return fallbackName;
  // UTF-8 filename*= formati
  const utf8Match = contentDisposition.match(/filename\*=(?:UTF-8''|utf-8'')([^;\s]+)/i);
  if (utf8Match) {
    try { return decodeURIComponent(utf8Match[1]) || fallbackName; } catch { /* fall through */ }
  }
  // Oddiy filename="..." yoki filename=...
  const match = contentDisposition.match(/filename="([^"]+)"/i)
    || contentDisposition.match(/filename=([^;\s]+)/i);
  const name = match ? match[1].trim() : '';
  return name || fallbackName;
}
