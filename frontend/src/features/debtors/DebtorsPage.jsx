import { useMemo, useState } from 'react';
import { Link } from '@tanstack/react-router';
import { Wallet, ChevronDown, Hash, Download } from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState,
} from '@/components/common';
import { useDebtors } from './hooks';
import { PayDebtDialog } from './PayDebtDialog';
import { formatMoney } from '@/lib/money';
import { downloadFile } from '@/lib/download';
import { cn } from '@/lib/utils';

export default function DebtorsPage() {
  const [statusFilter, setStatusFilter] = useState('active');
  const [page, setPage] = useState(1);
  const [payTarget, setPayTarget] = useState(null);
  const [exporting, setExporting] = useState(false);

  const filters = useMemo(
    () => ({ status: statusFilter, page }),
    [statusFilter, page]
  );
  const { data, isLoading, isError, refetch } = useDebtors(filters);

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;
  const startIdx = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const endIdx = Math.min(page * pageSize, total);

  function handleStatusChange(e) {
    setStatusFilter(e.target.value);
    setPage(1);
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  async function handleExport() {
    setExporting(true);
    try {
      await downloadFile('/debtors/export', { status: statusFilter }, 'qarzdorlar.xlsx');
    } finally {
      setExporting(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Qarzdorlar"
        count={total || undefined}
        actions={
          <button
            type="button"
            onClick={handleExport}
            disabled={exporting}
            className="flex items-center gap-2 rounded-btn border border-info px-4 py-2.5 text-sm font-medium text-info-text transition-colors hover:bg-info-bg disabled:opacity-60"
          >
            <Download className="h-4 w-4" />{exporting ? 'Yuklanmoqda...' : 'Excel ga yuklash'}
          </button>
        }
      />

      {/* Filter bar */}
      <div className="flex items-center gap-3">
        <FilterSelect value={statusFilter} onChange={handleStatusChange} width="w-[200px]">
          <option value="active">Faqat faol qarzlar</option>
          <option value="all">Barchasi (faol + to'langan)</option>
        </FilterSelect>
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-5 py-3.5">#</th>
                <th className="px-3 py-3.5">Mijoz nomi</th>
                <th className="px-3 py-3.5">INN</th>
                <th className="px-3 py-3.5">To'lov turi</th>
                <th className="px-3 py-3.5">Qarz muddati</th>
                <th className="px-3 py-3.5">Holat</th>
                <th className="px-3 py-3.5 text-right">Qarz summasi</th>
                <th className="w-32 px-3 py-3.5 text-center">Amal</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan={8} className="py-16 text-center text-[var(--text-secondary)]">
                    Yuklanmoqda…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-16 text-center">
                    <EmptyState
                      title="Qarzdorlar topilmadi"
                      description={
                        statusFilter === 'active'
                          ? "Hozircha aktiv qarzdorlar yo'q"
                          : "Hech qanday qarz yozuvi mavjud emas"
                      }
                    />
                  </td>
                </tr>
              ) : (
                rows.map((debt, i) => (
                  <tr
                    key={debt.id}
                    className={cn(
                      'border-b border-[var(--border)] last:border-0',
                      i % 2 === 1 && 'bg-bg-light'
                    )}
                  >
                    <td className="px-5 py-3.5 text-[var(--text-secondary)]">{startIdx + i}</td>
                    <td className="px-3 py-3.5 font-semibold text-[var(--text-primary)]">
                      <Link
                        to="/debtors/$id"
                        params={{ id: String(debt.id) }}
                        className="transition-colors hover:text-primary"
                      >
                        {debt.client_name}
                      </Link>
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                      <span className="inline-flex items-center gap-1 font-mono">
                        <Hash className="h-3 w-3" />
                        {debt.client_inn}
                      </span>
                    </td>
                    <td className="px-3 py-3.5">
                      <PaymentTypeBadge type={debt.payment_type_snapshot} />
                    </td>
                    <td className="px-3 py-3.5">
                      <DurationBadge months={debt.months_overdue} />
                    </td>
                    <td className="px-3 py-3.5">
                      <StatusBadge status={debt.status} />
                    </td>
                    <td className="px-3 py-3.5 text-right font-semibold tabular-nums text-[var(--text-primary)]">
                      {formatMoney(debt.amount)} <span className="text-xs text-[var(--text-secondary)]">so'm</span>
                    </td>
                    <td className="px-3 py-3.5">
                      <div className="flex items-center justify-center">
                        {debt.status === 'active' ? (
                          <button
                            type="button"
                            onClick={() => setPayTarget(debt)}
                            className="flex items-center gap-1.5 rounded-btn bg-success px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:opacity-90"
                          >
                            <Wallet className="h-3.5 w-3.5" />
                            To'lash
                          </button>
                        ) : (
                          <span className="text-xs text-[var(--text-secondary)]">—</span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Footer pagination */}
        <div className="flex items-center justify-between border-t border-[var(--border)] px-5 py-4">
          <span className="text-[13px] text-[var(--text-secondary)]">
            {startIdx}–{endIdx} / {total} ta
          </span>
          {data && total > 0 && (
            <Pagination page={data.page} total={total} pageSize={pageSize} onPageChange={setPage} />
          )}
        </div>
      </div>

      <PayDebtDialog
        open={!!payTarget}
        onOpenChange={(v) => !v && setPayTarget(null)}
        debt={payTarget}
      />
    </div>
  );
}

function FilterSelect({ value, onChange, width, children }) {
  return (
    <div className={cn('relative', width)}>
      <select
        value={value}
        onChange={onChange}
        className="h-11 w-full appearance-none rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-4 pr-9 text-sm text-[var(--text-primary)] outline-none focus:ring-2 focus:ring-primary"
      >
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--text-secondary)]" />
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
    <span className={cn('inline-flex items-center rounded-xl px-2.5 py-1 text-[11px] font-medium', item.cls)}>
      {item.label}
    </span>
  );
}

/** months_overdue ga qarab rang darajasi (warning → danger). */
function DurationBadge({ months }) {
  const m = Number(months) || 0;
  let cls = 'bg-warning-bg text-warning-text';
  if (m >= 6) cls = 'bg-danger-bg text-danger-text';
  else if (m >= 3) cls = 'bg-[#FFEDD5] text-[#EA580C]';
  return (
    <span className={cn('inline-flex items-center rounded-xl px-2.5 py-1 text-[11px] font-semibold', cls)}>
      {m} oy
    </span>
  );
}

function StatusBadge({ status }) {
  if (status === 'paid') {
    return (
      <span className="inline-flex items-center rounded-xl bg-success-bg px-2.5 py-1 text-[11px] font-medium text-success-text">
        To'langan
      </span>
    );
  }
  return (
    <span className="inline-flex items-center rounded-xl bg-danger-bg px-2.5 py-1 text-[11px] font-medium text-danger-text">
      Faol qarz
    </span>
  );
}
