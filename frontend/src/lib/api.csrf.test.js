// Feature: smartpay-crm-frontend, Integration: CSRF Double-Submit (R1.6)
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import api from './api';
import { server } from '@/test/server';

describe('api — CSRF integratsiya (R1.6)', () => {
  const originalCookie = document.cookie;

  beforeEach(() => {
    document.cookie = 'csrf_token=test-csrf-12345; path=/';
  });

  afterEach(() => {
    document.cookie = `csrf_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
  });

  it("POST so'rovi `X-CSRF-Token` header bilan yuboriladi", async () => {
    let receivedHeader = null;
    server.use(
      http.post('*/api/clients', ({ request }) => {
        receivedHeader = request.headers.get('x-csrf-token');
        return HttpResponse.json({ id: 1 }, { status: 201 });
      })
    );

    await api.post('/clients', { name: 'Test' });

    expect(receivedHeader).toBe('test-csrf-12345');
  });

  it("DELETE so'rovi ham `X-CSRF-Token` header'ini qo'shadi", async () => {
    let receivedHeader = null;
    server.use(
      http.delete('*/api/clients/:id', ({ request }) => {
        receivedHeader = request.headers.get('x-csrf-token');
        return HttpResponse.json({ ok: true });
      })
    );

    await api.delete('/clients/1');

    expect(receivedHeader).toBe('test-csrf-12345');
  });

  it("GET so'rovi `X-CSRF-Token` header qo'shilmaydi (state-changing emas)", async () => {
    let receivedHeader = 'NOT-SET';
    server.use(
      http.get('*/api/clients', ({ request }) => {
        receivedHeader = request.headers.get('x-csrf-token');
        return HttpResponse.json({ data: [], total: 0, page: 1, pageSize: 20 });
      })
    );

    await api.get('/clients');

    expect(receivedHeader).toBeNull();
  });

  it("/auth/login uchun CSRF header qo'shilmaydi (auth URL)", async () => {
    let receivedHeader = 'NOT-SET';
    server.use(
      http.post('*/api/auth/login', ({ request }) => {
        receivedHeader = request.headers.get('x-csrf-token');
        return HttpResponse.json({ user: { id: 1 } });
      })
    );

    await api.post('/auth/login', { email: 'a@b.uz', password: 'x' });

    expect(receivedHeader).toBeNull();
  });
});
