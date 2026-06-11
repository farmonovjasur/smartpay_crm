/**
 * @param {number} total
 * @param {number} pageSize
 * @returns {number}
 */
export function computeTotalPages(total, pageSize) {
  if (pageSize <= 0) return 1;
  return Math.max(1, Math.ceil(total / pageSize));
}

/**
 * Page ni [1, totalPages] oralig'iga cheklaydi.
 * @param {number} page
 * @param {number} totalPages
 * @returns {number}
 */
export function clampPage(page, totalPages) {
  return Math.max(1, Math.min(page, totalPages));
}

/**
 * Backend javobini yagona {data, total, page, pageSize} shaklga keltiradi.
 * @param {Object} res
 * @returns {import('../types/models').Paginated<any>}
 */
export function normalizePage(res) {
  return {
    data: res.items || res.data || [],
    total: res.total ?? 0,
    page: res.page ?? 1,
    pageSize: res.pageSize ?? res.page_size ?? 20,
  };
}
