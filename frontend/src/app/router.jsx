import { lazy } from 'react';
import { createRouter, createRootRoute, createRoute, redirect } from '@tanstack/react-router';
import { requireAuth, requireAdmin, requireGuest } from './guards';

const LoginPage = lazy(() => import('@/features/auth/LoginPage'));
const AppLayout = lazy(() => import('@/layouts/AppLayout'));
const DashboardPage = lazy(() => import('@/features/dashboard/DashboardPage'));
const ClientsPage = lazy(() => import('@/features/clients/ClientsPage'));
const ClientDetailPage = lazy(() => import('@/features/clients/ClientDetailPage'));
const InvoicesPage = lazy(() => import('@/features/invoices/InvoicesPage'));
const InvoiceDetailPage = lazy(() => import('@/features/invoices/InvoiceDetailPage'));
const DebtorsPage = lazy(() => import('@/features/debtors/DebtorsPage'));
const DebtorDetailPage = lazy(() => import('@/features/debtors/DebtorDetailPage'));
const NotificationsPage = lazy(() => import('@/features/notifications/NotificationsPage'));
const UsersPage = lazy(() => import('@/features/users/UsersPage'));
const AuditLogPage = lazy(() => import('@/features/audit/AuditLogPage'));

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
const clientsRoute = createRoute({ getParentRoute: () => authLayoutRoute, path: '/clients', component: ClientsPage });
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
