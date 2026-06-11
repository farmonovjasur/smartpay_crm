import { useState } from 'react';
import { Link, useParams } from '@tanstack/react-router';
import {
  ArrowLeft, Wallet, Hash, Calendar, CheckCircle2, AlertTriangle,
} from 'lucide-react';
import { ErrorState, LoadingState } from '@/components/common';
import { useDebtor } from './hooks';
import { PayDebtDialog } from './PayDebtDialog';
import { formatMoney } from '@/lib/money';
import { formatDate, formatPeriod } from '@/lib/date';
import { cn } from '@/lib/utils';

export default function DebtorDetailPage() {
  const { id } = useParams({ from: '/debtors/$id' });
  const { data: debt, isLoading, isError, refetch } = useDebtor(id);
  const [payOpen, setPayOpen] = useState(false);

  if (isLoading) return <LoadingState />;
  if (isError || !debt) return <ErrorState onRetry={refetch} />;

  const isActive = debt.status === 'active';

  return (
    <div className="space-y-6">
      {/* Top bar */}
      <div className="flex items-center justify-between">
        <Link
          to="/debtors"
          className="inline-flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
        >
          <ArrowLeft className="h-4 w-4" />
          Qarzdorlar ro'yxatiga qaytish
        </Link>

        {isActive && (
          <button
            type="button"
            onClick={() => setPayOpen(true)}
            className="flex items-center gap-2 rounded-btn bg-success px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90"
          >
            <Wallet className="h-4 w-4" />
            Qarzni to'lash
          </button>
        )}
      </div>

      {/* Hero card */}
      <div
        className={cn(
          'rounded-card border bg-[var(--card-bg)] p-6 shadow-sm',
          isActive ? 'border-danger/30' : 'border-success/30'
        )}
      >
        <div className="flex flex-wrap items-start justify-between gap-6">
          <div className="flex items-start gap-4">
            <div
              className={cn(
                'flex h-12 w-12 shrink-0 items-center justify-center rounded-md',
                isActive ? 'bg-danger-bg' : 'bg-success-bg'
              )}
            >
              {isActive ? (
                <AlertTriangle className="h-6 w-6 text-danger" />
              ) : (
                <CheckCircle2 className="h-6 w-6 text-success" />
              )}
            </div>
            <div className="space-y-2">
              <h1 className="text-xl font-bold leading-tight text-[var(--text-primary)]">
                {debt.client_name}
              </h1>
              <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-1.5 rounded-xl bg-bg-light px-2.5 py-1 text-xs text-[var(--text-secondary)]">
                  <Hash className="h-3 w-3" />
                  INN: <span className="font-medium text-[var(--text-primary)]">{debt.client_inn}</span>
                </span>
                <PaymentTypeBadge type={debt.payment_type_snapshot} />
                <StatusBadge status={debt.status} />
              </div>
            </div>
          </div>

          <div className="space-y-1 text-right">
            <span className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
              Qarz summasi
            </span>
            <p className="text-3xl font-bold text-[var(--text-primary)]">
              {formatMoney(debt.amount)}{' '}
              <span className="text-base font-medium text-[var(--text-secondary)]">so'm</span>
            </p>
            <p className="text-xs text-[var(--text-secondary)]">
              Oylik: {formatMoney(debt.monthly_amount)} so'm
            </p>
          </div>
        </div>
      </div>

      {/* Detail grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section title="Qarz davrlari" icon={Calendar}>
          <Row label="Birinchi o'tib ketgan davr" value={formatPeriod(debt.first_overdue_period)} />
          <Row label="So'nggi o'tib ketgan davr" value={formatPeriod(debt.last_overdue_period)} />
          <Row label="Davomiyligi" value={`${debt.months_overdue ?? 0} oy`} />
        </Section>

        <Section
          title={isActive ? "To'lov ma'lumotlari" : "To'lov amalga oshirildi"}
          icon={isActive ? Wallet : CheckCircle2}
        >
          {isActive ? (
            <>
              <Row label="To'lov holati" value={<StatusBadge status={debt.status} />} />
              <Row label="To'lanishi kerak" value={`${formatMoney(debt.amount)} so'm`} mono />
            </>
          ) : (
            <>
              <Row label="To'langan sana" value={debt.paid_at ? formatDate(debt.paid_at) : '—'} />
              <Row label="To'lov usuli" value={renderMethod(debt.paid_method)} />
              <Row label="To'langan summa" value={`${formatMoney(debt.amount)} so'm`} mono />
            </>
          )}
        </Section>
      </div>

      <PayDebtDialog open={payOpen} onOpenChange={setPayOpen} debt={debt} />
    </div>
  );
}

function Section({ title, icon: Icon, children }) {
  return (
    <section className="space-y-4 rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
      <header className="flex items-center gap-2">
        {Icon && <Icon className="h-4 w-4 text-[var(--text-secondary)]" />}
        <h2 className="text-sm font-semibold uppercase tracking-wide text-[var(--text-secondary)]">{title}</h2>
      </header>
      <div className="space-y-3">{children}</div>
    </section>
  );
}

function Row({ label, value, mono }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-[var(--border)] pb-3 last:border-0 last:pb-0">
      <span className="text-sm text-[var(--text-secondary)]">{label}</span>
      <span className={cn('text-sm font-medium text-[var(--text-primary)]', mono && 'tabular-nums')}>
        {value || '—'}
      </span>
    </div>
  );
}

function PaymentTypeBadge({ type }) {
  const map = {
    fakt: { cls: 'bg-primary-bg text-primary', label: 'Fakt' },
    naqt: { cls: 'bg-teal-bg text-teal', label: 'Naqt' },
    qarz: { cls: 'bg-warning-bg text-warning-text', label: 'Qarz' },
  };
  const item = map[type] || { cls: 'bg-[var(--hover-bg)] text-[var(--text-secondary)]', label: type || '—' };
  return (
    <span className={cn('inline-flex items-center rounded-xl px-2.5 py-1 text-xs font-medium', item.cls)}>
      {item.label}
    </span>
  );
}

function StatusBadge({ status }) {
  if (status === 'paid') {
    return (
      <span className="inline-flex items-center gap-1 rounded-xl bg-success-bg px-2.5 py-1 text-xs font-medium text-success-text">
        <CheckCircle2 className="h-3.5 w-3.5" />
        To'langan
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 rounded-xl bg-danger-bg px-2.5 py-1 text-xs font-medium text-danger-text">
      <AlertTriangle className="h-3.5 w-3.5" />
      Faol qarz
    </span>
  );
}

function renderMethod(method) {
  if (!method) return '—';
  if (method === 'fakt') return 'Fakt (online)';
  if (method === 'naqt') return 'Naqt';
  return method;
}
