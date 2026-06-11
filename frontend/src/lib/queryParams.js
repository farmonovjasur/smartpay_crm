/**
 * Filtrlardan API params quradi.
 * Standartlar: page=1, pageSize=20, sort=id_desc.
 * pageSize [1,100] ga clamp qilinadi.
 * @param {Object} filters
 * @returns {Object}
 */
export function buildListParams(filters = {}) {
  const params = { ...filters };
  params.page = params.page || 1;
  params.pageSize = Math.max(1, Math.min(params.pageSize || 20, 100));
  params.sort = params.sort || 'id_desc';
  // bo'sh qiymatlarni olib tashlash
  Object.keys(params).forEach((k) => {
    if (params[k] === '' || params[k] === null || params[k] === undefined) {
      delete params[k];
    }
  });
  return params;
}
