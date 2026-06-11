import { http, HttpResponse } from 'msw';

// Default base for axios is '/api' (Vite proxy emas — jsdom origin). MSW http://localhost'ga oid.
const API = '*/api';

const adminUser = { id: 1, name: 'Halimov Bekzod', email: 'admin@smartpay.uz', role: 'admin', is_active: true };

/**
 * MSW default handlers — har bir test alohida `server.use(...)` bilan override qila oladi.
 */
export const handlers = [
  // ─── Auth ───
  http.post(`${API}/auth/login`, async ({ request }) => {
    const body = await request.json().catch(() => ({}));
    if (body?.email === 'admin@smartpay.uz' && body?.password === 'correct') {
      return HttpResponse.json({ user: adminUser });
    }
    return HttpResponse.json({ message: 'Invalid' }, { status: 401 });
  }),
  http.get(`${API}/auth/me`, () => {
    // Default — guest. Testlar `server.use(...)` bilan override qiladi.
    return HttpResponse.json({ message: 'Unauthorized' }, { status: 401 });
  }),
  http.post(`${API}/auth/refresh`, () => HttpResponse.json({ message: 'Unauthorized' }, { status: 401 })),
  http.post(`${API}/auth/logout`, () => HttpResponse.json({ ok: true })),

  // ─── Clients ───
  http.get(`${API}/clients`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),
  http.post(`${API}/clients`, async ({ request }) => {
    const body = await request.json().catch(() => ({}));
    return HttpResponse.json({ id: 1, ...body }, { status: 201 });
  }),
  http.get(`${API}/clients/:id`, ({ params }) =>
    HttpResponse.json({
      id: Number(params.id),
      inn: '123456789',
      name: 'Test Client',
      phone: '+998901234567',
      service_date: '2026-01-15',
      payment_type: 'fakt',
      product_count: 1,
      status: 'faol',
    })
  ),
  http.put(`${API}/clients/:id`, async ({ request }) => {
    const body = await request.json().catch(() => ({}));
    return HttpResponse.json({ id: 1, ...body });
  }),
  http.delete(`${API}/clients/:id`, () => HttpResponse.json({ ok: true })),
  http.post(`${API}/clients/:id/mark-monthly-paid`, async ({ request }) => {
    const body = await request.json().catch(() => ({}));
    return HttpResponse.json({ status: 'paid', method: body.method, period: body.period });
  }),

  // ─── Invoices ───
  http.get(`${API}/invoices`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),
  http.post(`${API}/invoices/generate`, async ({ request }) => {
    const body = await request.json().catch(() => ({}));
    return HttpResponse.json(
      { id: 1, invoice_number: `FAKTURA-${body.period}-001`, period: body.period, total_amount: '1000000', items_count: 5 },
      { status: 201 }
    );
  }),

  // ─── Debtors ───
  http.get(`${API}/debtors`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),
  http.post(`${API}/debtors/:id/pay`, () => HttpResponse.json({ ok: true })),

  // ─── Notifications ───
  http.get(`${API}/notifications`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),

  // ─── Users ───
  http.get(`${API}/users`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),

  // ─── Audit logs ───
  http.get(`${API}/audit-logs`, () =>
    HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 })
  ),

  // ─── Dashboard ───
  http.get(`${API}/dashboard/stats`, () =>
    HttpResponse.json({
      activeClients: 0,
      debtorsCount: 0,
      totalDebt: '0',
      invoicesThisMonth: 0,
      monthlyChart: [],
      byPaymentType: { fakt: 0, naqt: 0, qarz: 0 },
      debtorsBreakdown: { fromFakt: 0, fromNaqt: 0, fromQarz: 0 },
    })
  ),
];

export const guestSession = (extra = []) => [
  http.get(`${API}/auth/me`, () => HttpResponse.json({ message: 'Unauthorized' }, { status: 401 })),
  ...extra,
];

export const adminSession = (extra = []) => [
  http.get(`${API}/auth/me`, () => HttpResponse.json({ user: adminUser })),
  ...extra,
];

export const userSession = (extra = []) => [
  http.get(`${API}/auth/me`, () =>
    HttpResponse.json({ user: { ...adminUser, id: 2, name: 'Karimov Jasur', email: 'jasur@smartpay.uz', role: 'user' } })
  ),
  ...extra,
];
