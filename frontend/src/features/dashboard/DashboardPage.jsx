import { Link } from '@tanstack/react-router';
import { Users, TriangleAlert, Banknote, FileText, TrendingUp, TrendingDown, Calendar, ArrowRight } from 'lucide-react';
import { Bar, Doughnut } from 'react-chartjs-2';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend } from 'chart.js';
import { LoadingState, ErrorState } from '@/components/common';
import { useAuth } from '@/features/auth/hooks';
import { useDashboardStats } from './hooks';
import { useAuditLogs } from '@/features/audit/hooks';
import { useActionMeta } from '@/features/audit/actionMeta';
import { formatMoney } from '@/lib/money';
import { formatDate } from '@/lib/date';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend);

function usePeriodToLabel() {
  const t = useT();
  return (period) => {
    if (!period || typeof period !== 'string') return period;
    const [, m] = period.split('-');
    const idx = Number(m);
    return t(`dashboard.months.${idx}`) || period;
  };
}

function formatDateTime(iso) {
  if (!iso) return { date: '—', time: '' };
  const d = new Date(iso);
  if (isNaN(d.getTime())) return { date: iso, time: '' };
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  return { date: formatDate(iso), time: `${hh}:${mm}` };
}

export default function DashboardPage() {
  const t = useT();
  const { isAdmin } = useAuth();
  const { data, isLoading, isError, refetch } = useDashboardStats();
  const recent = useAuditLogs({ page: 1, pageSize: 5 }, { enabled: isAdmin });
  const periodToLabel = usePeriodToLabel();
  const getActionMeta = useActionMeta();

  if (isLoading) return <LoadingState />;
  if (isError) return <ErrorState onRetry={refetch} />;

  const {
    activeClients,
    debtorsCount,
    totalDebt,
    invoicesThisMonth,
    monthlyChart = [],
    byPaymentType,
  } = data || {};

  const recentRows = recent.data?.data || [];

  return (
    <div className="space-y-5">
      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          accent="primary"
          icon={Users}
          iconBg="bg-primary-bg"
          iconColor="text-primary-text"
          label={t('dashboard.activeClients')}
          value={activeClients ?? '—'}
          trend={{ icon: TrendingUp, color: 'text-success-text', text: t('dashboard.activeClientsTrend') }}
        />
        <StatCard
          accent="warning"
          icon={TriangleAlert}
          iconBg="bg-[#FEF3C7]"
          iconColor="text-warning-text"
          label={t('dashboard.currentDebtors')}
          value={debtorsCount ?? '—'}
          trend={{ icon: TrendingDown, color: 'text-warning-text', text: t('dashboard.debtorsTrend') }}
        />
        <StatCard
          accent="danger"
          icon={Banknote}
          iconBg="bg-danger-bg"
          iconColor="text-danger-text"
          label={t('dashboard.unpaidDebts')}
          value={`${formatMoney(totalDebt)} ${t('common.som')}`}
          valueClass="text-[28px]"
          trend={{ icon: TrendingUp, color: 'text-danger-text', text: t('dashboard.unpaidDebtsTrend') }}
        />
        <StatCard
          accent="success"
          icon={FileText}
          iconBg="bg-success-bg"
          iconColor="text-success-text"
          label={t('dashboard.monthlyInvoices')}
          value={invoicesThisMonth ?? '—'}
          trend={{ icon: Calendar, color: 'text-[var(--text-secondary)]', text: t('dashboard.monthlyInvoicesTrend') }}
        />
      </div>

      {/* Charts */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Bar chart */}
        <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm lg:col-span-2">
          <div className="mb-5 flex items-start justify-between">
            <div>
              <h3 className="text-base font-semibold text-[var(--text-primary)]">{t('dashboard.chartTitle')}</h3>
              <p className="text-xs text-[var(--text-secondary)]">{t('dashboard.chartSubtitle')}</p>
            </div>
            <div className="flex items-center gap-4">
              <ChartLegend color="bg-primary" label={t('dashboard.chartFakt')} />
              <ChartLegend color="bg-teal" label={t('dashboard.chartNaqt')} />
            </div>
          </div>
          {monthlyChart.length > 0 ? (
            <div className="h-[240px]">
              <Bar
                data={{
                  labels: monthlyChart.map((p) => periodToLabel(p.period)),
                  datasets: [
                    { label: t('clients.paymentType.fakt'), data: monthlyChart.map((p) => Number(p.fakt_amount ?? p.fakt ?? 0)), backgroundColor: '#6366F1', borderRadius: 4, barPercentage: 0.6 },
                    { label: t('clients.paymentType.naqt'), data: monthlyChart.map((p) => Number(p.naqt_amount ?? p.naqt ?? 0)), backgroundColor: '#14B8A6', borderRadius: 4, barPercentage: 0.6 },
                  ],
                }}
                options={{
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: { legend: { display: false } },
                  scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#F1F5F9' } } },
                }}
              />
            </div>
          ) : (
            <p className="py-12 text-center text-sm text-[var(--text-secondary)]">{t('common.noData')}</p>
          )}
        </div>

        {/* Donut chart */}
        <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
          <div className="mb-4">
            <h3 className="text-base font-semibold text-[var(--text-primary)]">{t('dashboard.donutTitle')}</h3>
            <p className="text-xs text-[var(--text-secondary)]">{t('dashboard.donutSubtitle', { count: activeClients ?? 0 })}</p>
          </div>
          {byPaymentType ? (
            <div className="flex flex-col items-center gap-4">
              <div className="relative h-[200px] w-[200px]">
                <Doughnut
                  data={{
                    labels: [t('clients.paymentType.fakt'), t('clients.paymentType.naqt'), t('clients.paymentType.qarz')],
                    datasets: [{ data: [byPaymentType.fakt, byPaymentType.naqt, byPaymentType.qarz], backgroundColor: ['#6366F1', '#14B8A6', '#F59E0B'], borderWidth: 0 }],
                  }}
                  options={{ responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }}
                />
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                  <span className="text-2xl font-bold text-[var(--text-primary)]">{activeClients ?? 0}</span>
                  <span className="text-[11px] text-[var(--text-secondary)]">{t('dashboard.donutCenter')}</span>
                </div>
              </div>
              <div className="w-full space-y-2">
                <DonutLegend color="bg-primary" label={t('clients.paymentType.fakt')} value={byPaymentType.fakt} />
                <DonutLegend color="bg-teal" label={t('clients.paymentType.naqt')} value={byPaymentType.naqt} />
                <DonutLegend color="bg-warning" label={t('clients.paymentType.qarz')} value={byPaymentType.qarz} />
              </div>
            </div>
          ) : (
            <p className="py-12 text-center text-sm text-[var(--text-secondary)]">{t('common.noData')}</p>
          )}
        </div>
      </div>

      {/* So'nggi amallar (faqat admin) */}
      {isAdmin && (
        <div className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
          <div className="flex items-center justify-between border-b border-[var(--border)] px-6 py-5">
            <div>
              <h3 className="text-base font-semibold text-[var(--text-primary)]">{t('dashboard.recentActions')}</h3>
              <p className="text-xs text-[var(--text-secondary)]">{t('dashboard.recentActionsDesc')}</p>
            </div>
            <Link to="/audit-logs" className="flex items-center gap-1.5 text-[13px] font-medium text-primary hover:underline">
              {t('dashboard.viewAll')}
              <ArrowRight className="h-3.5 w-3.5" />
            </Link>
          </div>

          {recent.isLoading ? (
            <p className="py-10 text-center text-sm text-[var(--text-secondary)]">{t('common.loading')}</p>
          ) : recentRows.length === 0 ? (
            <p className="py-10 text-center text-sm text-[var(--text-secondary)]">{t('dashboard.noActions')}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                  <th className="px-6 py-3">{t('common.date')}</th>
                  <th className="px-3 py-3">{t('audit.col.action')}</th>
                  <th className="px-3 py-3">{t('audit.col.entity')}</th>
                  <th className="px-3 py-3">{t('audit.col.user')}</th>
                </tr>
              </thead>
              <tbody>
                {recentRows.map((log) => {
                  const meta = getActionMeta(log.action);
                  const dt = formatDateTime(log.created_at);
                  return (
                    <tr key={log.id} className="border-b border-[var(--border)] last:border-0">
                      <td className="px-6 py-3.5">
                        <div className="font-medium text-[var(--text-primary)]">{dt.date}</div>
                        <div className="text-[11px] text-[var(--text-secondary)]">{dt.time}</div>
                      </td>
                      <td className="px-3 py-3.5">
                        <div className="flex items-center gap-2.5">
                          <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[#0F172A11]">
                            <meta.icon className={cn('h-3.5 w-3.5', meta.color)} />
                          </span>
                          <span className="font-medium text-[var(--text-primary)]">{meta.label}</span>
                        </div>
                      </td>
                      <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                        {log.entity_type}{log.entity_id ? ` #${log.entity_id}` : ''}
                      </td>
                      <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                        {log.user_id ? `#${log.user_id}` : t('audit.system')}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      )}
    </div>
  );
}

function StatCard({ accent, icon: Icon, iconBg, iconColor, label, value, valueClass, trend }) {
  const accentBorder = {
    primary: 'border-l-primary',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
    success: 'border-l-success',
  }[accent];

  return (
    <div className={cn('rounded-card border border-[var(--border)] border-l-4 bg-[var(--card-bg)] p-5 shadow-sm', accentBorder)}>
      <div className="flex items-center justify-between">
        <span className="text-[13px] font-medium text-[var(--text-secondary)]">{label}</span>
        <div className={cn('flex h-10 w-10 items-center justify-center rounded-full', iconBg)}>
          <Icon className={cn('h-5 w-5', iconColor)} />
        </div>
      </div>
      <p className={cn('mt-3 text-[30px] font-bold leading-none text-[var(--text-primary)]', valueClass)}>{value}</p>
      {trend && (
        <div className={cn('mt-3 flex items-center gap-1.5 text-xs font-medium', trend.color)}>
          <trend.icon className="h-3.5 w-3.5" />
          <span>{trend.text}</span>
        </div>
      )}
    </div>
  );
}

function ChartLegend({ color, label }) {
  return (
    <div className="flex items-center gap-1.5">
      <span className={cn('h-2.5 w-2.5 rounded-sm', color)} />
      <span className="text-xs text-[var(--text-secondary)]">{label}</span>
    </div>
  );
}

function DonutLegend({ color, label, value }) {
  return (
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-2">
        <span className={cn('h-2.5 w-2.5 rounded-sm', color)} />
        <span className="text-sm text-[var(--text-primary)]">{label}</span>
      </div>
      <span className="text-sm font-semibold text-[var(--text-primary)]">{value}</span>
    </div>
  );
}
