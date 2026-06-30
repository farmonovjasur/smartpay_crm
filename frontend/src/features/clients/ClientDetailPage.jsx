import { useState } from 'react';
import { Link, useParams } from '@tanstack/react-router';
import {
  ArrowLeft, Pencil, CheckCircle2, Phone, Hash, Calendar,
  Package, CreditCard, Banknote, ClipboardList, FileText,
  Wallet, Plus
} from 'lucide-react';
import { ErrorState, LoadingState } from '@/components/common';
import { useClient } from './hooks';
import { ClientForm } from './ClientForm';
import { MarkMonthlyPaidDialog } from './MarkMonthlyPaidDialog';
import { PrepayDialog } from './PrepayDialog';
import { PrepaymentHistory } from './PrepaymentHistory';
import { PaymentHistory } from './PaymentHistory';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

const PAYMENT_META = {
  fakt: { label: 'Fakt (online)', icon: CreditCard, badge: 'bg-primary-bg text-primary' },
  naqt: { label: 'Naqt', icon: Banknote, badge: 'bg-teal-bg text-teal' },
  qarz: { label: 'Qarz', icon: ClipboardList, badge: 'bg-warning-bg text-warning-text' },
};

export default function ClientDetailPage() {
  const { id } = useParams({ strict: false });
  const { data: client, isLoading, isError, refetch } = useClient(id);
  const [editOpen, setEditOpen] = useState(false);
  const [markOpen, setMarkOpen] = useState(false);
  const [prepayOpen, setPrepayOpen] = useState(false);

  if (isLoading) return <LoadingState />;
  if (isError || !client) return <ErrorState onRetry={refetch} />;

  const payment = PAYMENT_META[client.payment_type] || PAYMENT_META.fakt;
  const PaymentIcon = payment.icon;
  // R10.5: `fakt` mijozlariga "Oylik to'lovni belgilash" birlamchi taklif qilinmaydi.
  const showMarkPaid = client.payment_type !== 'fakt';
  const isActive = client.status === 'faol';

  return (
    <div className="space-y-6">
      {/* Top bar: orqaga qaytish + actions */}
      <div className="flex items-center justify-between">
        <Link
          to="/clients"
          className="inline-flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
        >
          <ArrowLeft className="h-4 w-4" />
          Mijozlar ro'yxatiga qaytish
        </Link>

        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            onClick={() => setPrepayOpen(true)}
            className="flex items-center gap-2 rounded-btn border border-primary bg-primary-bg px-4 py-2.5 text-sm font-medium text-primary transition-colors hover:bg-primary hover:text-white"
          >
            <Plus className="h-4 w-4" />
            Oldindan to'lov
          </button>
          
          {showMarkPaid && (
            <button
              type="button"
              onClick={() => setMarkOpen(true)}
              className="flex items-center gap-2 rounded-btn border border-success bg-[var(--card-bg)] px-4 py-2.5 text-sm font-medium text-success-text transition-colors hover:bg-success-bg"
            >
              <CheckCircle2 className="h-4 w-4" />
              Oylik to'lovni belgilash
            </button>
          )}
          <button
            type="button"
            onClick={() => setEditOpen(true)}
            className="flex items-center gap-2 rounded-btn bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
          >
            <Pencil className="h-4 w-4" />
            Tahrirlash
          </button>
        </div>
      </div>

      {/* Hero card: ism + INN + payment + status + qarz holati */}
      <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-xl font-bold text-white">
              {client.name?.[0]?.toUpperCase() || 'M'}
            </div>
            <div className="space-y-2">
              <h1 className="text-2xl font-bold leading-tight text-[var(--text-primary)]">{client.name}</h1>
              <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-1.5 text-xs text-[var(--text-secondary)]">
                  <Hash className="h-3.5 w-3.5" />
                  INN: <span className="font-medium text-[var(--text-primary)]">{client.inn}</span>
                </span>
                <span className={cn('inline-flex items-center gap-1.5 rounded-xl px-2.5 py-1 text-[11px] font-medium', payment.badge)}>
                  <PaymentIcon className="h-3.5 w-3.5" />
                  {payment.label}
                </span>
                <StatusPill active={isActive} />
                <DebtPill hasDebt={client.has_active_debt} />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Balans */}
      <div className="rounded-card border border-[var(--border)] bg-gradient-to-br from-[var(--card-bg)] to-bg-light p-6 shadow-sm">
        <div className="flex items-center gap-4">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-bg text-teal">
            <Wallet className="h-6 w-6" />
          </div>
          <div>
            <p className="text-sm font-medium text-[var(--text-secondary)]">Joriy balans</p>
            <div className="mt-1 flex items-baseline gap-2">
              <span className="text-3xl font-bold tracking-tight text-[var(--text-primary)]">
                {formatMoney(client.balance || 0)}
              </span>
              <span className="text-sm font-medium text-[var(--text-secondary)]">UZS</span>
            </div>
            {client.monthly_amount && Number(client.balance) > 0 && (
              <p className="mt-1 text-xs text-success-text font-medium">
                Taxminan {Math.floor(Number(client.balance) / Number(client.monthly_amount))} oyga yetadi
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Info grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section title="Aloqa ma'lumotlari" icon={Phone}>
          <Row label="Telefon" value={client.phone} mono />
          {client.phone2 && <Row label="Qo'shimcha telefon" value={client.phone2} mono />}
          <Row label="INN" value={client.inn} mono />
        </Section>

        <Section title="Xizmat ma'lumotlari" icon={Package}>
          <Row label="Ulangan sana" value={formatDate(client.service_date)} icon={Calendar} />
          <Row label="Mahsulot soni" value={`${client.product_count ?? 0} ta`} />
          <Row label="To'lov turi" value={payment.label} />
          {client.last_paid_period ? (
            <Row label="Oxirgi to'langan davr" value={formatPeriod(client.last_paid_period)} icon={Calendar} />
          ) : null}
          {client.monthly_amount != null && (
            <Row label="Oylik summa" value={String(client.monthly_amount)} mono />
          )}
        </Section>
      </div>

      {/* Notes */}
      {client.notes ? (
        <Section title="Izohlar" icon={FileText}>
          <p className="text-sm leading-relaxed text-[var(--text-primary)] whitespace-pre-wrap">{client.notes}</p>
        </Section>
      ) : null}

      {/* Payment and Prepayment Histories */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <PaymentHistory clientId={id} />
        <PrepaymentHistory clientId={id} />
      </div>

      {/* Dialoglar */}
      <ClientForm open={editOpen} onOpenChange={setEditOpen} client={client} />
      <MarkMonthlyPaidDialog open={markOpen} onOpenChange={setMarkOpen} client={client} />
      <PrepayDialog open={prepayOpen} onOpenChange={setPrepayOpen} client={client} />
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

function Row({ label, value, mono, icon: Icon }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-[var(--border)] pb-3 last:border-0 last:pb-0">
      <span className="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
        {Icon && <Icon className="h-4 w-4" />}
        {label}
      </span>
      <span className={cn('text-sm font-medium text-[var(--text-primary)]', mono && 'font-mono')}>{value || '—'}</span>
    </div>
  );
}

function StatusPill({ active }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-xl bg-bg-light px-2.5 py-1 text-[11px] font-medium">
      <span className={cn('h-2 w-2 rounded-full', active ? 'bg-success' : 'bg-[#94A3B8]')} />
      <span className={active ? 'text-success-text' : 'text-[var(--text-secondary)]'}>
        {active ? 'Faol' : 'Nofaol'}
      </span>
    </span>
  );
}

function DebtPill({ hasDebt }) {
  return hasDebt ? (
    <span className="inline-flex items-center rounded-xl bg-danger-bg px-2.5 py-1 text-[11px] font-medium text-danger-text">
      Qarzdor
    </span>
  ) : (
    <span className="inline-flex items-center rounded-xl bg-success-bg px-2.5 py-1 text-[11px] font-medium text-success-text">
      Qarzsiz
    </span>
  );
}
