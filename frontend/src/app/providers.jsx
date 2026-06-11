import { Suspense } from 'react';
import { Provider, useSelector } from 'react-redux';
import { QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from '@tanstack/react-router';
import { Toaster } from 'sonner';
import { store } from '@/store';
import { queryClient } from '@/lib/queryClient';
import { router } from './router';
import { LoadingState } from '@/components/common';
import { useBootstrap } from '@/features/auth/hooks';
import { useTheme } from '@/hooks/useTheme';
import { I18nProvider } from '@/lib/i18n';

/**
 * Ilova yuklanganda GET /api/auth/me orqali sessiyani tiklaydi.
 * Sessiya holati aniqlanmaguncha (idle/loading) loading ko'rsatadi —
 * shunda guardlar noto'g'ri redirect qilmaydi (Requirement 1.9–1.12, 3.7).
 */
function AuthBootstrap({ children }) {
  const status = useSelector((s) => s.auth.status);
  useBootstrap();
  // Tema rejimini ilova yuklangandayoq `<html>` ga qo'llash.
  useTheme();

  if (status === 'idle' || status === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--bg-light)]">
        <LoadingState text="Yuklanmoqda…" />
      </div>
    );
  }

  return children;
}

export function AppProviders() {
  return (
    <I18nProvider>
      <Provider store={store}>
        <QueryClientProvider client={queryClient}>
          <AuthBootstrap>
            <Suspense fallback={<LoadingState />}>
              <RouterProvider router={router} />
            </Suspense>
          </AuthBootstrap>
          <Toaster position="top-right" richColors closeButton />
        </QueryClientProvider>
      </Provider>
    </I18nProvider>
  );
}
