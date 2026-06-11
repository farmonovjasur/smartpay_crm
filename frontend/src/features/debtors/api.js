import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Backend Debt javobini snake_case'ga normallashtiradi.
 * @param {any} d
 */
export function normalizeDebt(d) {
  if (!d || typeof d !== 'object') return d;
  return {
    id: d.id,
    client_id: d.client_id ?? d.clientId,
    client_name: d.client_name ?? d.clientName ?? '',
    client_inn: d.client_inn ?? d.clientInn ?? '',
    amount: d.amount ?? '0',
    monthly_amount: d.monthly_amount ?? d.monthlyAmount ?? '0',
    months_overdue: d.months_overdue ?? d.monthsOverdue ?? 0,
    payment_type_snapshot: d.payment_type_snapshot ?? d.paymentTypeSnapshot ?? '',
    status: d.status ?? 'active',
    first_overdue_period: d.first_overdue_period ?? d.firstOverduePeriod ?? '',
    last_overdue_period: d.last_overdue_period ?? d.lastOverduePeriod ?? '',
    paid_at: d.paid_at ?? d.paidAt ?? null,
    paid_method: d.paid_method ?? d.paidMethod ?? null,
    created_at: d.created_at ?? d.createdAt ?? null,
  };
}

export const debtorsApi = {
  list: (filters) => api.get('/debtors', { params: buildListParams(filters) }).then((r) => r.data),
  get: (id) => api.get(`/debtors/${id}`).then((r) => r.data),
  pay: (id, body) => api.post(`/debtors/${id}/pay`, body).then((r) => r.data),
};
