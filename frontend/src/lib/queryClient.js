import { QueryClient } from '@tanstack/react-query';
import { showError } from './toast';
import { mapErrorToMessage } from './errors';

/**
 * Mutation uchun global onError fallback'i.
 * Per-mutation onError mavjud bo'lsa global ishlamaydi (TanStack Query v5).
 * 401 — auth interceptor refresh qiladi yoki login sahifasiga yo'naltiradi (Toast bermaymiz).
 */
function globalMutationErrorHandler(error) {
  const status = error?.response?.status ?? null;
  if (status === 401) return;
  const msg =
    error?.normalized?.message ||
    mapErrorToMessage({
      status,
      body: error?.response?.data,
      isNetwork: !error?.response,
    });
  showError(msg);
}

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: (failureCount, error) => {
        const status = error?.response?.status;
        // 4xx xatolarini takrorlamaymiz — server javob berdi.
        if (status && status >= 400 && status < 500) return false;
        return failureCount < 1;
      },
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
    mutations: {
      onError: globalMutationErrorHandler,
    },
  },
});
