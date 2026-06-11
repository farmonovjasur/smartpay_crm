import React from 'react';
import { render } from '@testing-library/react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import authReducer, { setUser, clearAuth } from '@/store/authSlice';
import uiReducer from '@/store/uiSlice';

/**
 * Test uchun freshQueryClient — har testda izolyatsiya.
 */
export function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0, staleTime: 0 },
      mutations: { retry: false },
    },
    logger: { log: () => {}, warn: () => {}, error: () => {} },
  });
}

/**
 * Test uchun yangi Redux store.
 * @param {{ user?: object|null, status?: 'idle'|'loading'|'authed'|'guest' }} [authState]
 */
export function createTestStore(authState) {
  const store = configureStore({
    reducer: { auth: authReducer, ui: uiReducer },
  });
  if (authState?.user) {
    store.dispatch(setUser(authState.user));
  } else if (authState?.status === 'guest') {
    store.dispatch(clearAuth());
  }
  return store;
}

/**
 * Render helper — Redux + ReactQuery providers ichida.
 *
 * @param {React.ReactElement} ui
 * @param {{ authState?: any, queryClient?: QueryClient, store?: any }} [options]
 */
export function renderWithProviders(ui, options = {}) {
  const queryClient = options.queryClient || createTestQueryClient();
  const store = options.store || createTestStore(options.authState);

  function Wrapper({ children }) {
    return (
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
      </Provider>
    );
  }

  return {
    ...render(ui, { wrapper: Wrapper }),
    queryClient,
    store,
  };
}
