import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Backend ClientOutput camelCase qaytaradi (serviceDate, paymentType, productCount).
 * UI barqaror snake_case shaklga tayanadi — shu yerda normallashtiramiz.
 * @param {any} c
 */
export function normalizeClient(c) {
  if (!c || typeof c !== 'object') return c;
  return {
    id: c.id,
    inn: c.inn,
    name: c.name,
    phone: c.phone,
    phone2: c.phone2 ?? c.phone2 ?? null,
    service_date: c.service_date ?? c.serviceDate ?? '',
    payment_type: c.payment_type ?? c.paymentType ?? '',
    product_count: c.product_count ?? c.productCount ?? 0,
    status: c.status,
    notes: c.notes ?? '',
    last_paid_period: c.last_paid_period ?? c.lastPaidPeriod ?? null,
    // Backend hozircha qarz holatini ClientOutput'da qaytarmaydi — bo'lsa o'qiymiz.
    has_active_debt: c.has_active_debt ?? c.hasActiveDebt ?? false,
    monthly_amount: c.monthly_amount ?? c.monthlyAmount ?? null,
    created_at: c.created_at ?? c.createdAt ?? null,
  };
}

export const clientsApi = {
  list: (filters) => api.get('/clients', { params: buildListParams(filters) }).then((r) => r.data),
  get: (id) => api.get(`/clients/${id}`).then((r) => r.data),
  create: (data) => api.post('/clients', data).then((r) => r.data),
  update: (id, data) => api.put(`/clients/${id}`, data).then((r) => r.data),
  remove: (id) => api.delete(`/clients/${id}`),
  markMonthlyPaid: (id, body) => api.post(`/clients/${id}/mark-monthly-paid`, body).then((r) => r.data),
};
