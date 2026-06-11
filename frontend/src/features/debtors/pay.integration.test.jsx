// Feature: smartpay-crm-frontend, Integration: usePayDebt 409 (R9.5)
import React from 'react';
import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { Provider } from 'react-redux';
import { QueryClientProvider } from '@tanstack/react-query';
import { server } from '@/test/server';
import { createTestQueryClient, createTestStore } from '@/test/utils.jsx';
import { usePayDebt } from './hooks';

describe('usePayDebt — integration (R9.5)', () => {
  beforeEach(() => server.resetHandlers());

  function setup() {
    const qc = createTestQueryClient();
    const store = createTestStore({ status: 'guest' });
    const Wrapper = ({ children }) => (
      <Provider store={store}>
        <QueryClientProvider client={qc}>{children}</QueryClientProvider>
      </Provider>
    );
    return { qc, store, Wrapper };
  }

  it("muvaffaqiyatli to'lov mutation isSuccess=true qiladi", async () => {
    const { Wrapper } = setup();
    const { result } = renderHook(() => usePayDebt(42), { wrapper: Wrapper });

    result.current.mutate({ method: 'fakt' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
  });

  it("409 javobida xato statusi 409 bo'ladi (R9.5)", async () => {
    server.use(
      http.post('*/api/debtors/:id/pay', () =>
        HttpResponse.json({ message: 'Already paid' }, { status: 409 })
      )
    );

    const { Wrapper } = setup();
    const { result } = renderHook(() => usePayDebt(42), { wrapper: Wrapper });

    result.current.mutate({ method: 'fakt' });

    await waitFor(() => expect(result.current.isError).toBe(true));
    expect(result.current.error?.response?.status).toBe(409);
  });
});
