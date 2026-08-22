import { useMemo, useState } from 'react';
import { Link } from '@tanstack/react-router';
import {
  Plus, Search, Download, ChevronDown, Hash, Wallet, Eye,
} from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState,
} from '@/components/common';
import { useLedgerEntries } from './hooks';
import { AddLedgerDialog } from './AddLedgerDialog';
import { PayLedgerDialog } from './PayLedgerDialog';
import { useDebounce } from '@/lib/useDebounce';
import { formatMoney } from '@/lib/money';
import { formatDate } from '@/lib/date';
import { downloadFile } from '@/lib/download';
import { cn } from '@/lib/utils';

export default function LedgerPage() {
  const [statusFilter, setStatusFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [addOpen, setAddOpen] = useState(false);
  const [payTarget, setPayTarget] = useState(null);
  const [exporting, setExporting] = useState(false);
  const debouncedSearch = useDebounce(search, 400);

  const filters = useMemo(
    () => ({ status: statusFilter, search: debouncedSearch || undefined, page }),
    [statusFilter, debouncedSearch, page]
  );
  const { data, isLoading, isError, refetch } = useLedgerEntries(filters);

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;
  const startIdx = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const endIdx = Math.min(page * pageSize, total);

  function handleStatusChange(e) {
    setStatusFilter(e.target.value);
    setPage(1);
  }

  async function handleExport() {
    setExporting(true);
    try {
      await downloadFile(
        '/ledger/export',
        { status: statusFilter, search: debouncedSearch || undefined },
        'qarzdaftari.xlsx'
      );
    } finally {
      setExporting(false);
    }
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Qarzdaftari"
        count={total || undefined}
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleExport}
              disabled={exporting}
              className="flex items-center gap-2 rounded-btn border border-info px-4 py-2.5 text-sm font-medium text-info-text transition-colors hover:bg-info-bg disabled:opacity-60"
            >
              <Download className="h-4 w-4" />
              {exporting ? 'Yuklanmoqda...' : 'Excel'}
            </button>
            <button
              type="button"
              onClick={() => setAddOpen(true)}
              className="flex items-center gap-2 rounded-btn bg-primary px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90"
            >
              <Plus className="h-4 w-4" />
              Qo'shish
            </button>
          </div>
        }
      />

      {/* Filter bar */}
      <div className="flex items-center gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-secondary)]" />
          <input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder="Qidiruv (ism, INN, telefon)..."
            className="h-11 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-11 pr-4 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary"
          />
        </div>
        <FilterSelect value={statusFilter} onChange={handleStatusChange} width="w-[200px]">
          <option value="all">Barchasi</option>
          <option value="active">Faol qarzlar</option>
          <option value="partial">Qisman to'langan</option>
          <option value="paid">To'langan</option>
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
                <th className="px-3 py-3.5">Telefon</th>
                <th className="px-3 py-3.5 text-center">Xizmatlar</th>
                <th className="px-3 py-3.5">Holat</th>
                <th className="px-3 py-3.5 text-right">Jami summa</th>
                <th className="px-3 py-3.5 text-right">To'langan</th>
                <th className="w-40 px-3 py-3.5 text-center">Amal</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan={9} className="py-16 text-center text-[var(--text-secondary)]">
                    Yuklanmoqda…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={9} className="py-16 text-center">
                    <EmptyState
                      title="Qarzdaftarda yozuvlar yo'q"
                      description={'Yangi qarz qo\'shish uchun "Qo\'shish" tugmasini bosing'}
                    />
                  </td>
                </tr>
              ) : (
                rows.map((entry, i) => (
                  <tr
                    key={entry.id}
                    className={cn(
                      'border-b border-[var(--border)] last:border-0',
                      i % 2 === 1 && 'bg-bg-light'
                    )}
                  >
                    <td className="px-5 py-3.5 text-[var(--text-secondary)]">{startIdx + i}</td>
                    <td className="px-3 py-3.5 font-semibold text-[var(--text-primary)]">
                      <Link
                        to="/ledger/$id"
                        params={{ id: String(entry.id) }}
                        className="transition-colors hover:text-primary"
                      >
                        {entry.client_name}
                      </Link>
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                      <span className="inline-flex items-center gap-1 font-mono">
                        <Hash className="h-3 w-3" />
                        {entry.client_inn}
                      </span>
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">
                      {entry.client_phone}
                    </td>
                    <td className="px-3 py-3.5 text-center">
                      <span className="inline-flex items-center rounded-xl bg-[var(--hover-bg)] px-2.5 py-1 text-[11px] font-medium text-[var(--text-secondary)]">
                        {entry.items_count} ta
                      </span>
                    </td>
                    <td className="px-3 py-3.5">
                      <StatusBadge status={entry.status} />
                    </td>
                    <td className="px-3 py-3.5 text-right font-semibold tabular-nums text-[var(--text-primary)]">
                      {entry.status === 'partial' ? (
                        <div>
                          <span className="text-[var(--text-secondary)] line-through text-xs">
                            {formatMoney(entry.total_amount)}
                          </span>
                          <br />
                          {formatMoney(entry.remaining_amount)}{' '}
                          <span className="text-xs text-[var(--text-secondary)]">so'm</span>
                        </div>
                      ) : (
                        <>
                          {formatMoney(entry.total_amount)}{' '}
                          <span className="text-xs text-[var(--text-secondary)]">so'm</span>
                        </>
                      )}
                    </td>
                    <td className="px-3 py-3.5 text-right tabular-nums">
                      {parseFloat(entry.paid_amount) > 0 ? (
                        <span className="font-semibold text-success">
                          {formatMoney(entry.paid_amount)}{' '}
                          <span className="text-xs">so'm</span>
                        </span>
                      ) : (
                        <span className="text-xs text-[var(--text-secondary)]">—</span>
                      )}
                    </td>
                    <td className="px-3 py-3.5">
                      <div className="flex items-center justify-center gap-1.5">
                        {(entry.status === 'active' || entry.status === 'partial') && (
                          <button
                            type="button"
                            onClick={() => setPayTarget(entry)}
                            className="flex items-center gap-1.5 rounded-btn bg-success px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:opacity-90"
                          >
                            <Wallet className="h-3.5 w-3.5" />
                            To'lash
                          </button>
                        )}
                        <Link
                          to="/ledger/$id"
                          params={{ id: String(entry.id) }}
                          className="inline-flex items-center gap-1 rounded-lg border border-[var(--border)] bg-[var(--card-bg)] px-2.5 py-1.5 text-xs font-medium text-[var(--text-secondary)] transition-all hover:border-primary hover:bg-primary-bg hover:text-primary"
                        >
                          <Eye className="h-3.5 w-3.5" />
                          Ko'rish
                        </Link>
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

      <AddLedgerDialog
        open={addOpen}
        onOpenChange={setAddOpen}
      />

      <PayLedgerDialog
        open={!!payTarget}
        onOpenChange={(v) => !v && setPayTarget(null)}
        entry={payTarget}
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

function StatusBadge({ status }) {
  if (status === 'paid') {
    return (
      <span className="inline-flex items-center rounded-xl bg-success-bg px-2.5 py-1 text-[11px] font-medium text-success-text">
        To'langan
      </span>
    );
  }
  if (status === 'partial') {
    return (
      <span className="inline-flex items-center rounded-xl bg-warning-bg px-2.5 py-1 text-[11px] font-medium text-warning-text">
        Qisman
      </span>
    );
  }
  return (
    <span className="inline-flex items-center rounded-xl bg-danger-bg px-2.5 py-1 text-[11px] font-medium text-danger-text">
      Faol qarz
    </span>
  );
}
