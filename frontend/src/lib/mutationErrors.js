import { mapErrorToMessage } from './errors';
import { mapFieldErrors } from './serverErrors';
import { showError } from './toast';

/**
 * @typedef {Object} MutationErrorOptions
 * @property {(field: string, error: { message: string }) => void} [setError]
 *   RHF `setError` funksiyasi — 422 va kontekstli xato xabarlarini formaga yozish uchun.
 * @property {string[]} [fields]
 *   Forma maydon nomlari (422 mapping uchun). Mos kelmagan xatolar `root` ga yoki Toast'ga o'tadi.
 * @property {Object<number, string|((err: any) => string|null|void)>} [statusMessages]
 *   Kontekstga mos status → xabar (masalan `{409: 'Bu INN mavjud'}`).
 *   Funksiya bo'lsa va `null/undefined` qaytarsa — fallback Toast ishlatiladi.
 * @property {string} [conflictField]
 *   `statusMessages[409]` xabari shu field ostida ko'rsatiladi (Toast o'rniga).
 *   `setError` mavjud bo'lsagina ishlaydi.
 */

/**
 * Mutation onError uchun standart handler.
 *
 * Tartibi (R15):
 * 1. 422 + `errors` obyekti + `setError`+`fields` → maydon-darajasidagi xatolar (R15.1).
 * 2. `statusMessages[status]` → kontekstli xabar (forma yoki Toast). Masalan 409 (R15.2).
 * 3. Aks holda `mapErrorToMessage` orqali umumiy o'zbekcha Toast (R15.3–R15.7).
 *
 * @param {any} err — axios xatosi (api interceptor `error.normalized` ham qo'shadi).
 * @param {MutationErrorOptions} [options]
 * @returns {void}
 */
export function handleMutationError(err, options = {}) {
  const { setError, fields = [], statusMessages = {}, conflictField } = options;
  const status = err?.response?.status ?? null;
  const body = err?.response?.data;
  const isNetwork = !err?.response;

  // 1. 422 maydon xatolari
  if (status === 422 && body?.errors && setError && fields.length > 0) {
    const mapped = mapFieldErrors(body.errors, fields);
    let mappedAnything = false;
    for (const [k, v] of Object.entries(mapped)) {
      if (k === 'root') {
        showError(v);
      } else {
        setError(k, { message: v });
        mappedAnything = true;
      }
    }
    if (!mappedAnything && !mapped.root) {
      // Body bor lekin maydon nomlari mos kelmadi — Toast bilan ko'rsatamiz.
      showError(mapErrorToMessage({ status, body, isNetwork }));
    }
    return;
  }

  // 2. Kontekstli xabarlar (409, 413 va h.k.)
  if (status != null && Object.prototype.hasOwnProperty.call(statusMessages, status)) {
    const entry = statusMessages[status];
    const msg = typeof entry === 'function' ? entry(err) : entry;
    if (msg) {
      if (conflictField && setError) {
        setError(conflictField, { message: msg });
      } else {
        showError(msg);
      }
      return;
    }
    // Funksiya `null/undefined` qaytarsa fallback'ga o'tamiz.
  }

  // 3. Umumiy fallback
  showError(mapErrorToMessage({ status, body, isNetwork }));
}
