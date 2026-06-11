import { mapErrorToMessage } from './errors';
import { parseRetryAfter } from './retryAfter';

/**
 * Axios xatosini normallashtiradi.
 * @param {import('axios').AxiosError} axiosError
 * @returns {import('../types/models').NormalizedError}
 */
export function normalizeError(axiosError) {
  const response = axiosError?.response;
  const isNetwork = !response;
  const status = response?.status ?? null;
  const body = response?.data;
  const retryAfter = status === 429 ? parseRetryAfter(response?.headers?.['retry-after']) : null;
  const fieldErrors = status === 422 && body?.errors ? body.errors : null;
  const message = mapErrorToMessage({ status, body, isNetwork });
  return { status, isNetwork, fieldErrors, retryAfter, message };
}

/**
 * 422 maydon xatolarini forma field'lariga taqsimlaydi.
 * Mavjud field'ga mos bo'lmaganlar `root` ga qo'shiladi.
 * @param {Object} errors — backend'dan {field: [msg, ...]}
 * @param {string[]} formFields — formadagi maydon nomlari
 * @returns {Object} — {fieldName: msg, root?: msg}
 */
export function mapFieldErrors(errors, formFields) {
  const result = {};
  const unmapped = [];
  if (!errors || typeof errors !== 'object') return result;
  for (const [field, messages] of Object.entries(errors)) {
    const msg = Array.isArray(messages) ? messages[0] : messages;
    if (formFields.includes(field)) {
      result[field] = msg;
    } else {
      unmapped.push(msg);
    }
  }
  if (unmapped.length > 0) {
    result.root = unmapped.join('. ');
  }
  return result;
}
