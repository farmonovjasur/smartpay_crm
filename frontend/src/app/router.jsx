import { lazy } from 'react';
import { createRouter, createRootRoute, createRoute, redirect } from '@tanstack/react-router';
import { requireAuth, requireAdmin, requireGuest } from './guards';

/**
 * Lazy import xatolarini ushlab, sahifani avtomatik qayta yuklaydi.
 * Yangi deploy qilinganda eski chunk fayllari topilmasa,
 * foydalanuvchi uchun sahifa avtomatik refreshlanadi (faqat 1 marta).
 */
function lazyWithRetry(importFn) {
  return lazy(() =>
    importFn().catch((error) => {
      // Cheksiz reload loop'dan himoya
      const key = 'chunk_reload_' + window.location.pathname;
      const lastReload = sessionStorage.getItem(key);
      const now = Date.now();

      if (!lastReload || now - Number(lastReload) > 10000) {
        sessionStorage.setItem(key, String(now));
        window.location.reload();
      }

      throw error;
    })
  );
}

const LoginPage = lazyWithRetry(() => import('@/features/auth/LoginPage'));
const AppLayout = lazyWithRetry(() => import('@/layouts/AppLayout'));
const DashboardPage = lazyWithRetry(() => import('@/features/dashboard/DashboardPage'));
const ClientsPage = lazyWithRetry(() => import('@/features/clients/ClientsPage'));
const ClientDetailPage = lazyWithRetry(() => import('@/features/clients/ClientDetailPage'));
const InvoicesPage = lazyWithRetry(() => import('@/features/invoices/InvoicesPage'));
const InvoiceDetailPage = lazyWithRetry(() => import('@/features/invoices/InvoiceDetailPage'));
const DebtorsPage = lazyWithRetry(() => import('@/features/debtors/DebtorsPage'));
const DebtorDetailPage = lazyWithRetry(() => import('@/features/debtors/DebtorDetailPage'));
const NotificationsPage = lazyWithRetry(() => import('@/features/notifications/NotificationsPage'));
const UsersPage = lazyWithRetry(() => import('@/features/users/UsersPage'));
const AuditLogPage = lazyWithRetry(() => import('@/features/audit/AuditLogPage'));


const rootRoute = createRootRoute();

const indexRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/',
  beforeLoad: () => { throw redirect({ to: '/dashboard' }); },
});

const loginRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/login',
  beforeLoad: requireGuest,
  component: LoginPage,
});

const authLayoutRoute = createRoute({
  getParentRoute: () => rootRoute,
  id: 'authed',
  beforeLoad: requireAuth,
  component: AppLayout,
});

const dashboardRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/dashboard', component: DashboardPage });
const clientsRoute = createRoute({
  getParentRoute: () => authLayoutRoute,
  path: '/clients',
  component: ClientsPage,
  validateSearch: (search) => ({
    page: Number(search.page) || 1,
    search: search.search || '',
    payment_type: search.payment_type || '',
    status: search.status || '',
  }),
});
const clientDetailRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/clients/$id', component: ClientDetailPage });
const invoicesRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/invoices', component: InvoicesPage });
const invoiceDetailRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/invoices/$id', component: InvoiceDetailPage });
const debtorsRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/debtors', component: DebtorsPage });
const debtorDetailRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/debtors/$id', component: DebtorDetailPage });
const notificationsRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/notifications', component: NotificationsPage });
const usersRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/users', beforeLoad: requireAdmin, component: UsersPage });
const auditLogRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/audit-logs', beforeLoad: requireAdmin, component: AuditLogPage });

const routeTree = rootRoute.addChildren([
  indexRoute,
  loginRoute,
  authLayoutRoute.addChildren([
    dashboardRoute,
    clientsRoute,
    clientDetailRoute,
    invoicesRoute,
    invoiceDetailRoute,
    debtorsRoute,
    debtorDetailRoute,
    notificationsRoute,
    usersRoute,
    auditLogRoute,
  ]),
]);

export const router = createRouter({ routeTree, defaultPreload: 'intent' });
