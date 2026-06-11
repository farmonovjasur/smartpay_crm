import api from '@/lib/api';

/**
 * Backend Invoice javobini snake_case'ga normallashtiradi.
 * camelCase variantlarni ham qabul qiladi (mijozlardagi kabi).
 * @param {any} inv
 */
export function normalizeInvoice(inv) {
  if (!inv || typeof inv !== 'object') return inv;
  return {
    id: inv.id,
    invoice_number: inv.invoice_number ?? inv.invoiceNumber ?? '',
    period: inv.period ?? '',
    issue_date: inv.issue_date ?? inv.issueDate ?? '',
    total_amount: inv.total_amount ?? inv.totalAmount ?? '0',
    items_count: inv.items_count ?? inv.itemsCount ?? 0,
    responsible_name: inv.responsible_name ?? inv.responsibleName ?? '',
    unit_price_snapshot: inv.unit_price_snapshot ?? inv.unitPriceSnapshot ?? null,
    product_name_snapshot: inv.product_name_snapshot ?? inv.productNameSnapshot ?? null,
    items: Array.isArray(inv.items) ? inv.items.map(normalizeInvoiceItem) : undefined,
    created_at: inv.created_at ?? inv.createdAt ?? null,
  };
}

function normalizeInvoiceItem(it) {
  if (!it || typeof it !== 'object') return it;
  return {
    id: it.id,
    client_id: it.client_id ?? it.clientId,
    client_name: it.client_name ?? it.clientName ?? '',
    client_inn: it.client_inn ?? it.clientInn ?? '',
    quantity: it.quantity ?? 0,
    unit_price: it.unit_price ?? it.unitPrice ?? '0',
    total_price: it.total_price ?? it.totalPrice ?? '0',
    is_carried_debt: it.is_carried_debt ?? it.isCarriedDebt ?? false,
  };
}

export const invoicesApi = {
  list: (params) => api.get('/invoices', { params }).then((r) => r.data),
  get: (id) => api.get(`/invoices/${id}`).then((r) => r.data),
  generate: (body) => api.post('/invoices/generate', body).then((r) => r.data),
  remove: (id) => api.delete(`/invoices/${id}`),
};
