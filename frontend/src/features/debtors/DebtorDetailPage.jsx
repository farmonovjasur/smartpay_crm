import { useState } from 'react';
import { Link, useParams } from '@tanstack/react-router';
import {
  ArrowLeft, Wallet, Hash, Calendar, CheckCircle2, AlertTriangle,
  ExternalLink, Layers, Check, Clock, Sparkles, Receipt, Printer
} from 'lucide-react';
import { ErrorState, LoadingState } from '@/components/common';
import { useDebtor } from './hooks';
import { PayDebtDialog } from './PayDebtDialog';
import { PaymentReceipt } from '@/features/clients/PaymentReceipt';
import { formatMoney } from '@/lib/money';
import { formatDate, formatPeriod } from '@/lib/date';
import { cn } from '@/lib/utils';

export default function DebtorDetailPage() {
  const { id } = useParams({ strict: false });
  const { data: debt, isLoading, isError, refetch } = useDebtor(id);
  const [payOpen, setPayOpen] = useState(false);
  const [receiptOpen, setReceiptOpen] = useState(false);

  if (isLoading) return <LoadingState />;
  if (isError || !debt) return <ErrorState onRetry={refetch} />;

  const isActive = debt.status === 'active' || debt.status === 'partial';
  const isPaid = debt.status === 'paid';
  const hasPaidAmount = parseFloat(debt.paid_amount) > 0;
  const hasAdvance = parseFloat(debt.advance_amount || '0') > 0;
  const distributionItems = debt.distribution_items || [];

  return (
    <div className="space-y-6">
      {/* Top bar */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Link
          to="/debtors"
          className="inline-flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
        >
          <ArrowLeft className="h-4 w-4" />
          Qarzdorlar ro'yxatiga qaytish
        </Link>

        <div className="flex items-center gap-3">
          {debt.client_id && (
            <Link
              to="/clients/$id"
              params={{ id: String(debt.client_id) }}
              className="inline-flex items-center gap-1.5 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3.5 py-2 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-bg-light hover:text-[var(--text-primary)]"
            >
              <ExternalLink className="h-4 w-4" />
              Mijoz profiliga o'tish
            </Link>
          )}

          {(isPaid || hasPaidAmount) && (
            <button
              type="button"
              onClick={() => setReceiptOpen(true)}
              className="flex items-center gap-2 rounded-btn border border-primary/30 bg-primary-bg px-4 py-2 text-sm font-semibold text-primary transition-all hover:bg-primary hover:text-white shadow-sm"
              title="To'lov kvitansiyasini (chek) ko'rish va chop etish"
            >
              <Receipt className="h-4 w-4" />
              To'lov cheki
            </button>
          )}

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
                {debt.client_last_paid_period && (
                  <span className="inline-flex items-center gap-1 rounded-xl bg-primary-bg px-2.5 py-1 text-xs font-semibold text-primary">
                    <Sparkles className="h-3 w-3" />
                    To'langan davr: {formatPeriod(debt.client_last_paid_period)} gacha
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="space-y-1 text-right">
            <span className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
              {isActive ? 'Qolgan qarz' : 'Yopilgan qarz summasi'}
            </span>
            <p className="text-3xl font-bold text-[var(--text-primary)]">
              {formatMoney(isActive && hasPaidAmount ? debt.remaining_amount : debt.amount)}{' '}
              <span className="text-base font-medium text-[var(--text-secondary)]">so'm</span>
            </p>
            {isActive && hasPaidAmount && (
              <p className="text-xs text-success font-medium">
                To'langan: {formatMoney(debt.paid_amount)} so'm
              </p>
            )}
            {isPaid && (
              <p className="text-xs text-success font-semibold flex items-center justify-end gap-1">
                <Check className="h-3.5 w-3.5" /> Qarz to'liq yopilgan (Qoldiq: 0 so'm)
              </p>
            )}
            {hasAdvance && (
              <p className="text-xs text-teal font-semibold">
                Oldindan to'lov: +{formatMoney(debt.advance_amount)} so'm
              </p>
            )}
            <p className="text-xs text-[var(--text-secondary)]">
              Oylik abonent: {formatMoney(debt.monthly_amount)} so'm
            </p>
          </div>
        </div>
      </div>

      {/* Detail grid */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Section title={isActive ? "Qarz davrlari" : "Yopilgan qarz davrlari"} icon={Calendar}>
          <Row
            label={isActive ? "Birinchi o'tib ketgan davr" : "Boshlang'ich qarz davri"}
            value={formatPeriod(debt.first_overdue_period)}
          />
          <Row
            label={isActive ? "So'nggi o'tib ketgan davr" : "Yakuniy qarz davri"}
            value={formatPeriod(debt.last_overdue_period)}
          />
          <Row label="Qarz davomiyligi" value={`${debt.months_overdue ?? 0} oy`} />
          <Row
            label="Qarz holati"
            value={
              isPaid ? (
                <span className="inline-flex items-center gap-1 font-semibold text-success">
                  <CheckCircle2 className="h-4 w-4" /> To'liq yopilgan
                </span>
              ) : (
                <StatusBadge status={debt.status} />
              )
            }
          />
        </Section>

        <Section
          title={isActive ? "To'lov ma'lumotlari" : "To'lov amalga oshirildi"}
          icon={isActive ? Wallet : CheckCircle2}
        >
          {isActive ? (
            <>
              <Row label="To'lov holati" value={<StatusBadge status={debt.status} />} />
              <Row label="Umumiy qarz" value={`${formatMoney(debt.amount)} so'm`} mono />
              {hasPaidAmount && (
                <>
                  <Row label="To'langan" value={<span className="text-success font-semibold">{formatMoney(debt.paid_amount)} so'm</span>} />
                  {debt.paid_at && <Row label="Oxirgi to'lov sanasi" value={formatDate(debt.paid_at)} />}
                  <Row label="Qolgan qarz" value={`${formatMoney(debt.remaining_amount)} so'm`} mono />
                </>
              )}
              {!hasPaidAmount && (
                <Row label="To'lanishi kerak" value={`${formatMoney(debt.amount)} so'm`} mono />
              )}
            </>
          ) : (
            <>
              <Row label="To'langan sana" value={debt.paid_at ? formatDate(debt.paid_at) : '—'} />
              <Row label="To'lov usuli" value={renderMethod(debt.paid_method)} />
              <Row label="Qarz uchun yopilgan" value={`${formatMoney(debt.paid_amount || debt.amount)} so'm`} mono />
              {hasAdvance && (
                <>
                  <Row
                    label="Oldindan to'lov (avans)"
                    value={<span className="text-teal font-semibold">+{formatMoney(debt.advance_amount)} so'm</span>}
                    mono
                  />
                  <Row
                    label="Jami to'langan summa"
                    value={<span className="text-base font-bold text-success">{formatMoney(debt.total_transaction_amount)} so'm</span>}
                    mono
                  />
                  <Row
                    label="To'lov qamrovi"
                    value={<span className="font-semibold text-primary">{formatPeriod(debt.client_last_paid_period)} gacha</span>}
                  />
                </>
              )}
              {!hasAdvance && (
                <Row label="Jami to'langan" value={`${formatMoney(debt.paid_amount || debt.amount)} so'm`} mono />
              )}
              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => setReceiptOpen(true)}
                  className="flex w-full items-center justify-center gap-2 rounded-btn border border-[var(--border)] bg-bg-light py-2 text-xs font-semibold text-[var(--text-primary)] transition-all hover:border-primary hover:bg-primary-bg hover:text-primary"
                >
                  <Receipt className="h-3.5 w-3.5" />
                  To'lov chekini ko'rish va chop etish
                </button>
              </div>
            </>
          )}
        </Section>
      </div>

      {/* Breakdown / Distribution Section */}
      {distributionItems.length > 0 && (
        <section className="space-y-4 rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
          <header className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Layers className="h-4 w-4 text-[var(--text-secondary)]" />
              <h2 className="text-sm font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                Oylar bo'yicha to'lov taqsimoti
              </h2>
            </div>
            <div className="flex items-center gap-3">
              {debt.total_transaction_amount && (
                <span className="text-xs font-semibold text-[var(--text-secondary)]">
                  Jami: <span className="font-bold text-[var(--text-primary)]">{formatMoney(debt.total_transaction_amount)} so'm</span>
                </span>
              )}
              <button
                type="button"
                onClick={() => setReceiptOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border)] bg-[var(--card-bg)] px-2.5 py-1 text-xs font-semibold text-primary transition-all hover:bg-primary hover:text-white"
              >
                <Printer className="h-3.5 w-3.5" />
                Chek
              </button>
            </div>
          </header>

          <div className="divide-y divide-[var(--border)] rounded-xl border border-[var(--border)] bg-bg-light/50">
            {distributionItems.map((item, index) => {
              const isItemDebt = item.type === 'debt';
              return (
                <div
                  key={item.id || index}
                  className="flex flex-wrap items-center justify-between gap-3 p-4 transition-colors hover:bg-[var(--hover-bg)]"
                >
                  <div className="flex items-center gap-3">
                    <div
                      className={cn(
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
                        isItemDebt ? 'bg-success-bg text-success' : 'bg-primary-bg text-primary'
                      )}
                    >
                      {index + 1}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-[var(--text-primary)]">
                        {formatPeriod(item.period)}
                      </p>
                      <p className="text-xs text-[var(--text-secondary)]">
                        {isItemDebt ? "Eski qarz yopilishi" : "Kelgusi oy uchun oldindan to'lov (avans)"}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-4">
                    <span
                      className={cn(
                        'inline-flex items-center gap-1 rounded-xl px-2.5 py-1 text-xs font-semibold',
                        isItemDebt
                          ? 'bg-success-bg text-success'
                          : 'bg-teal-bg text-teal'
                      )}
                    >
                      {isItemDebt ? (
                        <>
                          <Check className="h-3 w-3" /> Qarz yopildi
                        </>
                      ) : (
                        <>
                          <Sparkles className="h-3 w-3" /> Oldindan to'lov
                        </>
                      )}
                    </span>
                    <span className="min-w-[100px] text-right font-mono text-sm font-bold tabular-nums text-[var(--text-primary)]">
                      {formatMoney(item.amount)} <span className="text-xs font-normal text-[var(--text-secondary)]">so'm</span>
                    </span>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-bg-light p-4 text-xs font-medium text-[var(--text-secondary)]">
            <span>
              Qarz yopilishi: <strong className="text-[var(--text-primary)]">{formatMoney(debt.paid_amount || debt.amount)} so'm</strong>
              {hasAdvance && (
                <> + Oldindan to'lov: <strong className="text-teal">{formatMoney(debt.advance_amount)} so'm</strong></>
              )}
            </span>
            {debt.client_last_paid_period && (
              <span className="font-semibold text-primary">
                Mijoz joriy holati: {formatPeriod(debt.client_last_paid_period)} gacha to'langan
              </span>
            )}
          </div>
        </section>
      )}

      <PayDebtDialog open={payOpen} onOpenChange={setPayOpen} debt={debt} />

      {/* Persistent Receipt Modal */}
      {(isPaid || hasPaidAmount) && (
        <PaymentReceipt
          open={receiptOpen}
          onOpenChange={setReceiptOpen}
          payment={debt.receipt || {
            receipt_id: `CHK-${String(debt.id).padStart(6, '0')}`,
            paid_at: debt.paid_at,
            payment_method: debt.paid_method || 'naqt',
            total_amount: debt.total_transaction_amount || debt.paid_amount,
            debt_amount_paid: debt.paid_amount,
            prepaid_amount: debt.advance_amount || '0.00',
            client: {
              id: debt.client_id,
              name: debt.client_name,
              inn: debt.client_inn,
              phone: debt.client_phone,
              balance: debt.balance,
              last_paid_period: debt.client_last_paid_period,
            },
            debt_items: distributionItems.filter((i) => i.type === 'debt'),
            prepaid_items: distributionItems.filter((i) => i.type === 'prepaid'),
            balance_item: debt.advance_amount && parseFloat(debt.advance_amount) > 0 ? {
              amount: debt.advance_amount,
              label: "Balansga tushgan summa",
            } : null,
            summary: {
              debt_remaining: debt.remaining_amount,
              new_balance: debt.balance,
              paid_up_to: debt.client_last_paid_period,
              months_debt_closed: distributionItems.filter((i) => i.type === 'debt').length,
              months_prepaid: distributionItems.filter((i) => i.type === 'prepaid').length,
            },
          }}
          client={{
            id: debt.client_id,
            name: debt.client_name,
            inn: debt.client_inn,
            phone: debt.client_phone,
            balance: debt.balance,
            last_paid_period: debt.client_last_paid_period,
          }}
        />
      )}
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
  if (status === 'partial') {
    return (
      <span className="inline-flex items-center gap-1 rounded-xl bg-warning-bg px-2.5 py-1 text-xs font-medium text-warning-text">
        <AlertTriangle className="h-3.5 w-3.5" />
        Qisman to'langan
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
