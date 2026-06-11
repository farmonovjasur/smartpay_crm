// Feature: smartpay-crm-frontend, Integration: Login oqimi (R1.1, R1.3, R1.4)
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import React from 'react';
import { Provider } from 'react-redux';
import { QueryClientProvider } from '@tanstack/react-query';
import { server } from '@/test/server';
import { createTestQueryClient, createTestStore } from '@/test/utils.jsx';
import { useLogin } from './hooks';

// Router import bo'sh bo'lishi uchun (useLogout uses router.navigate, login uchun emas).
vi.mock('@/app/router', () => ({ router: { navigate: vi.fn() } }));

function wrap(children) {
  const queryClient = createTestQueryClient();
  const store = createTestStore({ status: 'guest' });
  return ({ children: c }) => (
    <Provider store={store}>
      <QueryClientProvider client={queryClient}>{c}</QueryClientProvider>
    </Provider>
  );
}

describe('useLogin — integration', () => {
  beforeEach(() => server.resetHandlers());

  it('200 javobida user store ga saqlanadi', async () => {
    const queryClient = createTestQueryClient();
    const store = createTestStore({ status: 'guest' });

    const Wrapper = ({ children }) => (
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </Provider>
    );

    const { result } = renderHook(() => useLogin(), { wrapper: Wrapper });

    result.current.mutate({ email: 'admin@smartpay.uz', password: 'correct' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(store.getState().auth.user).toMatchObject({
      email: 'admin@smartpay.uz',
      role: 'admin',
    });
    expect(store.getState().auth.status).toBe('authed');
  });

  it("401 javobida user saqlanmaydi (R1.3)", async () => {
    const queryClient = createTestQueryClient();
    const store = createTestStore({ status: 'guest' });

    const Wrapper = ({ children }) => (
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </Provider>
    );

    const { result } = renderHook(() => useLogin(), { wrapper: Wrapper });

    result.current.mutate({ email: 'wrong@smartpay.uz', password: 'incorrect' });

    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(result.current.error?.response?.status).toBe(401);
    expect(store.getState().auth.user).toBeNull();
  });

  it("429 javobida Retry-After header xatoda mavjud bo'ladi (R1.4)", async () => {
    server.use(
      http.post('*/api/auth/login', () =>
        HttpResponse.json(
          { message: 'Too many requests' },
          { status: 429, headers: { 'Retry-After': '45' } }
        )
      )
    );

    const queryClient = createTestQueryClient();
    const store = createTestStore({ status: 'guest' });

    const Wrapper = ({ children }) => (
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </Provider>
    );

    const { result } = renderHook(() => useLogin(), { wrapper: Wrapper });

    result.current.mutate({ email: 'admin@smartpay.uz', password: 'correct' });

    await waitFor(() => expect(result.current.isError).toBe(true));
    const err = result.current.error;
    expect(err?.response?.status).toBe(429);
    expect(err?.response?.headers?.['retry-after']).toBe('45');
  });
});
