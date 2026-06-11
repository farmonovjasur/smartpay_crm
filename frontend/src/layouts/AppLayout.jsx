import { useEffect } from 'react';
import { Outlet, Link, useMatchRoute, useRouterState } from '@tanstack/react-router';
import { useSelector, useDispatch } from 'react-redux';
import {
  LayoutDashboard, Users, FileText, TriangleAlert, UserCog, LogOut,
  Moon, Sun, Bell, ChevronLeft, ChevronRight, Crown, Menu, X, ScrollText,
} from 'lucide-react';
import { useAuth, useLogout } from '@/features/auth/hooks';
import { toggleSidebar, setMobileMenuOpen } from '@/store/uiSlice';
import { useUnreadCount } from '@/features/notifications/hooks';
import { useTheme } from '@/hooks/useTheme';
import { useT, useLocale } from '@/lib/i18n';
import { LanguageSwitcher } from '@/components/common';
import { cn } from '@/lib/utils';

function useNavItems() {
  const t = useT();
  return [
    { to: '/dashboard', label: t('nav.dashboard'), icon: LayoutDashboard },
    { to: '/clients', label: t('nav.clients'), icon: Users },
    { to: '/invoices', label: t('nav.invoices'), icon: FileText },
    { to: '/debtors', label: t('nav.debtors'), icon: TriangleAlert },
  ];
}

function useAdminItems() {
  const t = useT();
  return [
    { to: '/users', label: t('nav.users'), icon: UserCog },
    { to: '/audit-logs', label: t('nav.auditLog'), icon: ScrollText },
  ];
}

function useRouteMeta(pathname) {
  const t = useT();
  const ROUTE_META = {
    '/dashboard': { title: t('routeMeta.dashboard.title'), crumb: t('routeMeta.dashboard.crumb') },
    '/clients': { title: t('routeMeta.clients.title'), crumb: t('routeMeta.clients.crumb') },
    '/invoices': { title: t('routeMeta.invoices.title'), crumb: t('routeMeta.invoices.crumb') },
    '/debtors': { title: t('routeMeta.debtors.title'), crumb: t('routeMeta.debtors.crumb') },
    '/notifications': { title: t('routeMeta.notifications.title'), crumb: t('routeMeta.notifications.crumb') },
    '/users': { title: t('routeMeta.users.title'), crumb: t('routeMeta.users.crumb') },
    '/audit-logs': { title: t('routeMeta.auditLogs.title'), crumb: t('routeMeta.auditLogs.crumb') },
  };
  const match = Object.keys(ROUTE_META).find((p) => pathname.startsWith(p));
  return ROUTE_META[match] || { title: 'SmartPay CRM', crumb: '' };
}

export default function AppLayout() {
  const t = useT();
  const { user, isAdmin } = useAuth();
  const logout = useLogout();
  const dispatch = useDispatch();
  const collapsed = !useSelector((s) => s.ui.sidebarOpen);
  const mobileMenuOpen = useSelector((s) => s.ui.mobileMenuOpen);
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const meta = useRouteMeta(pathname);
  const { data: unreadCount = 0 } = useUnreadCount();
  const { isDark, toggle: toggleDarkMode } = useTheme();

  const navItems = useNavItems();
  const adminItems = useAdminItems();

  // Marshrut o'zgarganda mobile drawer avtomatik yopiladi.
  useEffect(() => {
    dispatch(setMobileMenuOpen(false));
  }, [pathname, dispatch]);

  // Esc bosilganda drawer yopiladi.
  useEffect(() => {
    if (!mobileMenuOpen) return undefined;
    const onKey = (e) => {
      if (e.key === 'Escape') dispatch(setMobileMenuOpen(false));
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [mobileMenuOpen, dispatch]);

  // Drawer ochiq paytda body skroli bloklanadi.
  useEffect(() => {
    if (!mobileMenuOpen) return undefined;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = prev;
    };
  }, [mobileMenuOpen]);

  const items = isAdmin ? [...navItems, ...adminItems] : navItems;
  const initial = user?.name?.[0]?.toUpperCase() || 'U';

  return (
    <div className="flex h-screen overflow-hidden bg-[var(--bg-light)]">
      {/* Desktop sidebar — faqat lg+ */}
      <aside
        className={cn(
          'hidden lg:flex shrink-0 flex-col bg-[var(--sidebar-bg)] py-5 transition-[width] duration-200',
          collapsed ? 'w-[72px] px-3' : 'w-[260px] px-4'
        )}
      >
        <SidebarContent
          mode={collapsed ? 'collapsed' : 'expanded'}
          user={user}
          initial={initial}
          items={items}
          onToggle={() => dispatch(toggleSidebar())}
          onLogout={() => logout.mutate()}
        />
      </aside>

      {/* Mobile drawer — faqat <lg, ochiq holatda */}
      {mobileMenuOpen && (
        <div className="lg:hidden fixed inset-0 z-50">
          <button
            type="button"
            aria-label={t('layout.closeSidebar')}
            onClick={() => dispatch(setMobileMenuOpen(false))}
            className="absolute inset-0 bg-[#0F172A99]"
          />
          <aside
            className="relative flex h-full w-[280px] flex-col bg-[var(--sidebar-bg)] px-4 py-5"
            style={{ boxShadow: '8px 0 24px rgba(15, 23, 42, 0.25)' }}
          >
            <SidebarContent
              mode="drawer"
              user={user}
              initial={initial}
              items={items}
              onToggle={() => dispatch(setMobileMenuOpen(false))}
              onLogout={() => logout.mutate()}
            />
          </aside>
        </div>
      )}

      {/* Asosiy ustun: navbar + content */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Mobile navbar — faqat <lg */}
        <header className="flex h-14 shrink-0 items-center justify-between border-b border-[var(--border)] bg-[var(--navbar-bg)] px-4 lg:hidden">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => dispatch(setMobileMenuOpen(true))}
              className="flex h-9 w-9 items-center justify-center rounded-md text-[var(--text-primary)]"
              aria-label={t('layout.openMenu')}
            >
              <Menu className="h-[22px] w-[22px]" />
            </button>
            <h1 className="text-base font-bold text-[var(--text-primary)]">{meta.title}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageSwitcher />
            <button
              type="button"
              onClick={toggleDarkMode}
              className="flex h-9 w-9 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--bg-light)] text-[var(--text-secondary)]"
              aria-label={isDark ? t('layout.lightMode') : t('layout.darkMode')}
            >
              {isDark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
            </button>
            <Link
              to="/notifications"
              className="relative flex h-9 w-9 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--bg-light)] text-[var(--text-primary)]"
              aria-label={t('nav.notifications')}
            >
              <Bell className="h-5 w-5" />
              <UnreadBadge count={unreadCount} />
            </Link>
          </div>
        </header>

        {/* Desktop navbar — faqat lg+ */}
        <header className="hidden h-16 shrink-0 items-center justify-between border-b border-[var(--border)] bg-[var(--navbar-bg)] px-8 lg:flex">
          <div className="flex flex-col gap-0.5">
            <h1 className="text-[18px] font-bold leading-tight text-[var(--text-primary)]">{meta.title}</h1>
            <div className="flex items-center gap-1.5 text-xs text-[var(--text-secondary)]">
              <span>{t('layout.homePage')}</span>
              <ChevronRight className="h-3 w-3 text-[var(--text-secondary)]" />
              <span className="font-medium text-[var(--text-primary)]">{meta.crumb}</span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {/* Til toggle (UZ/RU) */}
            <LanguageSwitcher />
            {/* Tungi rejim toggle */}
            <button
              type="button"
              onClick={toggleDarkMode}
              className="flex h-9 w-9 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--bg-light)] text-[var(--text-secondary)] transition-colors hover:text-[var(--text-primary)]"
              aria-label={isDark ? t('layout.lightMode') : t('layout.darkMode')}
            >
              {isDark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
            </button>
            {/* Bell → bildirishnomalar */}
            <Link
              to="/notifications"
              className="relative flex h-9 w-9 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--bg-light)] text-[var(--text-secondary)]"
              aria-label={t('nav.notifications')}
            >
              <Bell className="h-[18px] w-[18px]" />
              <UnreadBadge count={unreadCount} />
            </Link>
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

/**
 * Sidebar ichidagi mazmun — uchta rejimda ishlatiladi:
 * - `expanded`: 260px (lg+), label'lar bilan, yig'ish ChevronLeft
 * - `collapsed`: 72px (lg+), faqat ikonkalar, yoyish ChevronRight
 * - `drawer`: 280px (mobile), label'lar bilan, yopish X
 */
function SidebarContent({ mode, user, initial, items, onToggle, onLogout }) {
  const t = useT();
  const collapsed = mode === 'collapsed';
  const drawer = mode === 'drawer';
  const ToggleIcon = drawer ? X : collapsed ? ChevronRight : ChevronLeft;
  const toggleLabel = drawer
    ? t('layout.closeSidebar')
    : collapsed
      ? t('layout.expandPanel')
      : t('layout.collapsePanel');

  return (
    <>
      {/* User header */}
      {collapsed ? (
        <div className="flex flex-col items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
            {initial}
          </div>
          <button
            type="button"
            onClick={onToggle}
            className="flex h-7 w-7 items-center justify-center rounded-md border border-[#33415588] text-slate-300 hover:text-white"
            aria-label={toggleLabel}
            title={toggleLabel}
          >
            <ToggleIcon className="h-3.5 w-3.5" />
          </button>
        </div>
      ) : (
        <div className="flex items-center gap-3 px-1 py-1.5">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-base font-bold text-white">
            {initial}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-bold text-white">{user?.name || t('layout.user')}</p>
            <RoleBadge role={user?.role} />
          </div>
          <button
            type="button"
            onClick={onToggle}
            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-[#33415588] text-slate-300 hover:text-white"
            aria-label={toggleLabel}
            title={toggleLabel}
          >
            <ToggleIcon className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      <div className="h-4" />

      {/* Nav */}
      <nav className="flex flex-1 flex-col gap-1.5 overflow-y-auto">
        {items.map((item) => (
          <NavItem key={item.to} {...item} collapsed={collapsed} />
        ))}
      </nav>

      {/* Logout */}
      <button
        type="button"
        onClick={onLogout}
        className={cn(
          'mt-2 flex items-center gap-3 rounded-btn px-3 py-2.5 text-sm font-semibold text-danger transition-colors hover:bg-danger/10',
          collapsed && 'justify-center'
        )}
        title={t('nav.logout')}
      >
        <LogOut className="h-5 w-5 shrink-0" />
        {!collapsed && <span>{t('nav.logout')}</span>}
      </button>
    </>
  );
}

function RoleBadge({ role }) {
  const t = useT();
  if (role === 'admin') {
    return (
      <span className="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-purple-bg px-3 py-[3px] text-xs font-semibold text-purple-text">
        <Crown className="h-3.5 w-3.5" />
        {t('roles.admin')}
      </span>
    );
  }
  return (
    <span className="mt-1.5 inline-flex items-center rounded-full bg-info-bg px-3 py-[3px] text-xs font-semibold text-info-text">
      {t('roles.user')}
    </span>
  );
}

function NavItem({ to, label, icon: Icon, collapsed }) {
  const matchRoute = useMatchRoute();
  const isActive = matchRoute({ to, fuzzy: true });

  return (
    <Link
      to={to}
      title={collapsed ? label : undefined}
      className={cn(
        'flex items-center gap-3 rounded-btn text-sm transition-colors',
        collapsed ? 'h-11 justify-center px-0' : 'px-3 py-2.5',
        isActive
          ? 'border-l-[3px] border-primary bg-primary/20 font-semibold text-white'
          : 'font-medium text-[#CBD5E1] hover:bg-white/5'
      )}
    >
      <Icon className={cn('h-5 w-5 shrink-0', isActive ? 'text-white' : 'text-[#94A3B8]')} />
      {!collapsed && <span>{label}</span>}
    </Link>
  );
}

/** Bell tugmasi ustidagi qizil badge — o'qilmaganlar soni. */
function UnreadBadge({ count }) {
  const t = useT();
  if (!count || count <= 0) return null;
  const display = count > 99 ? '99+' : String(count);
  return (
    <span
      className="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-[var(--badge-border)] bg-danger px-1 text-[10px] font-bold leading-none text-white"
      aria-label={t('layout.unreadNotifications', { count })}
    >
      {display}
    </span>
  );
}
