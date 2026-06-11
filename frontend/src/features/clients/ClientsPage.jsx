import { useState, useMemo } from 'react';
import { Link } from '@tanstack/react-router';
import { Plus, Search, Upload, Download, ChevronDown, Pencil, Trash2 } from 'lucide-react';
import { Pagination, PageHeader, ErrorState, ConfirmDialog, RoleGate } from '@/components/common';
import { useClients, useDeleteClient } from './hooks';
import { useDebounce } from '@/lib/useDebounce';
import { downloadFile } from '@/lib/download';
import { formatDate, formatPeriod } from '@/lib/date';
import { showSuccess } from '@/lib/toast';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { ClientForm } from './ClientForm';
import { ClientImportDialog } from './ClientImportDialog';

export default function ClientsPage() {
  const t = useT();
  const [search, setSearch] = useState('');
  const [paymentType, setPaymentType] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const [formOpen, setFormOpen] = useState(false);
  const [editClient, setEditClient] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [exporting, setExporting] = useState(false);
  const debouncedSearch = useDebounce(search, 400);

  const filters = useMemo(() => ({
    search: debouncedSearch || undefined,
    payment_type: paymentType || undefined,
    status: status || undefined,
    page,
  }), [debouncedSearch, paymentType, status, page]);

  const { data, isLoading, isError, refetch } = useClients(filters);
  const deleteClient = useDeleteClient();

  function handleSearchChange(v) { setSearch(v); setPage(1); }
  function handleFilterChange(setter) { return (e) => { setter(e.target.value); setPage(1); }; }

  function openCreate() { setEditClient(null); setFormOpen(true); }
  function openEdit(client) { setEditClient(client); setFormOpen(true); }

  async function handleExport() {
    setExporting(true);
    try { await downloadFile('/clients/export', { search: debouncedSearch, payment_type: paymentType, status }, 'mijozlar.xlsx'); }
    finally { setExporting(false); }
  }

  function confirmDelete() {
    if (!deleteTarget) return;
    deleteClient.mutate(deleteTarget.id, {
      onSuccess: () => { showSuccess(t('clients.deleted')); setDeleteTarget(null); },
    });
  }

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;
  const startIdx = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const endIdx = Math.min(page * pageSize, total);

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('clients.title')}
        count={total}
        actions={
          <>
            <button
              type="button"
              onClick={() => setImportOpen(true)}
              className="flex items-center gap-2 rounded-btn border border-success px-4 py-2.5 text-sm font-medium text-success-text transition-colors hover:bg-success-bg"
            >
              <Upload className="h-4 w-4" />{t('clients.excelImport')}
            </button>
            <button
              type="button"
              onClick={handleExport}
              disabled={exporting}
              className="flex items-center gap-2 rounded-btn border border-info px-4 py-2.5 text-sm font-medium text-info-text transition-colors hover:bg-info-bg disabled:opacity-60"
            >
              <Download className="h-4 w-4" />{exporting ? t('clients.exporting') : t('clients.excelExport')}
            </button>
            <button
              type="button"
              onClick={openCreate}
              className="flex items-center gap-2 rounded-btn bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
            >
              <Plus className="h-4 w-4" />{t('clients.newClient')}
            </button>
          </>
        }
      />

      {/* Filter bar */}
      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-secondary)]" />
          <input
            value={search}
            onChange={(e) => handleSearchChange(e.target.value)}
            placeholder={t('clients.searchPlaceholder')}
            className="h-11 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-11 pr-4 text-sm outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary"
          />
        </div>
        <FilterSelect value={paymentType} onChange={handleFilterChange(setPaymentType)} width="w-[180px]">
          <option value="">{t('clients.paymentType.all')}</option>
          <option value="fakt">{t('clients.paymentType.fakt')}</option>
          <option value="naqt">{t('clients.paymentType.naqt')}</option>
          <option value="qarz">{t('clients.paymentType.qarz')}</option>
        </FilterSelect>
        <FilterSelect value={status} onChange={handleFilterChange(setStatus)} width="w-[160px]">
          <option value="">{t('clients.statusFilter.all')}</option>
          <option value="faol">{t('clients.statusFilter.active')}</option>
          <option value="nofaol">{t('clients.statusFilter.inactive')}</option>
        </FilterSelect>
      </div>

      {/* Table card */}
      <div className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-5 py-3.5">{t('clients.col.index')}</th>
                <th className="px-3 py-3.5">{t('clients.col.name')}</th>
                <th className="px-3 py-3.5">{t('clients.col.inn')}</th>
                <th className="px-3 py-3.5">{t('clients.col.phone')}</th>
                <th className="px-3 py-3.5">{t('clients.col.serviceDate')}</th>
                <th className="px-3 py-3.5">{t('clients.col.lastPaid')}</th>
                <th className="px-3 py-3.5">{t('clients.col.paymentType')}</th>
                <th className="px-3 py-3.5">{t('clients.col.productCount')}</th>
                <th className="px-3 py-3.5">{t('clients.col.debtStatus')}</th>
                <th className="px-3 py-3.5">{t('clients.col.status')}</th>
                <th className="w-16 px-3 py-3.5 text-center">{t('clients.col.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr><td colSpan={11} className="py-16 text-center text-[var(--text-secondary)]">{t('common.loading')}</td></tr>
              ) : rows.length === 0 ? (
                <tr><td colSpan={11} className="py-16 text-center text-[var(--text-secondary)]">{t('clients.notFound')}</td></tr>
              ) : (
                rows.map((client, i) => (
                  <tr key={client.id} className={cn('border-b border-[var(--border)] last:border-0', i % 2 === 1 && 'bg-bg-light')}>
                    <td className="px-5 py-3.5 text-[var(--text-secondary)]">{startIdx + i}</td>
                    <td className="px-3 py-3.5 font-medium text-[var(--text-primary)]">
                      <Link
                        to="/clients/$id"
                        params={{ id: String(client.id) }}
                        className="text-[var(--text-primary)] transition-colors hover:text-primary"
                      >
                        {client.name}
                      </Link>
                    </td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">{client.inn}</td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">{client.phone}</td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">{formatDate(client.service_date)}</td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">{formatPeriod(client.last_paid_period)}</td>
                    <td className="px-3 py-3.5"><PaymentBadge type={client.payment_type} /></td>
                    <td className="px-3 py-3.5 text-[var(--text-secondary)]">{client.product_count} {t('common.ta')}</td>
                    <td className="px-3 py-3.5"><DebtBadge hasDebt={client.has_active_debt} /></td>
                    <td className="px-3 py-3.5"><StatusCell status={client.status} /></td>
                    <td className="px-3 py-3.5">
                      <div className="flex items-center justify-center gap-2">
                        <button type="button" onClick={() => openEdit(client)} className="text-primary hover:text-primary-hover" aria-label={t('common.edit')}>
                          <Pencil className="h-[18px] w-[18px]" />
                        </button>
                        <RoleGate roles="admin">
                          <button type="button" onClick={() => setDeleteTarget(client)} className="text-danger hover:text-danger-text" aria-label={t('common.delete')}>
                            <Trash2 className="h-[18px] w-[18px]" />
                          </button>
                        </RoleGate>
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
          <span className="text-[13px] text-[var(--text-secondary)]">{startIdx}-{endIdx} / {total} {t('common.ta')}</span>
          {data && <Pagination page={data.page} total={total} pageSize={pageSize} onPageChange={setPage} />}
        </div>
      </div>

      <ClientForm open={formOpen} onOpenChange={setFormOpen} client={editClient} />
      <ClientImportDialog open={importOpen} onOpenChange={setImportOpen} />
      <ConfirmDialog
        open={!!deleteTarget}
        onOpenChange={(v) => !v && setDeleteTarget(null)}
        title={t('clients.deleteClient')}
        description={deleteTarget ? t('clients.deleteConfirm', { name: deleteTarget.name, inn: deleteTarget.inn }) : ''}
        confirmLabel={t('common.confirm')}
        loading={deleteClient.isPending}
        onConfirm={confirmDelete}
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
        className="h-11 w-full appearance-none rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-4 pr-9 text-sm text-[var(--text-secondary)] outline-none focus:ring-2 focus:ring-primary"
      >
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--text-secondary)]" />
    </div>
  );
}

function PaymentBadge({ type }) {
  const t = useT();
  const map = {
    fakt: { cls: 'bg-primary-bg text-primary', label: t('clients.paymentType.fakt') },
    naqt: { cls: 'bg-teal-bg text-teal', label: t('clients.paymentType.naqt') },
    qarz: { cls: 'bg-warning-bg text-warning-text', label: t('clients.paymentType.qarz') },
  };
  const item = map[type] || { cls: 'bg-[var(--hover-bg)] text-[var(--text-secondary)]', label: type };
  return <span className={cn('inline-flex items-center rounded-xl px-2.5 py-1 text-[11px] font-medium', item.cls)}>{item.label}</span>;
}

function DebtBadge({ hasDebt }) {
  const t = useT();
  return hasDebt ? (
    <span className="inline-flex items-center rounded-xl bg-danger-bg px-2.5 py-1 text-[11px] font-medium text-danger-text">{t('clients.hasDebt')}</span>
  ) : (
    <span className="inline-flex items-center rounded-xl bg-success-bg px-2.5 py-1 text-[11px] font-medium text-success-text">{t('clients.noDebt')}</span>
  );
}

function StatusCell({ status }) {
  const t = useT();
  const active = status === 'faol';
  return (
    <span className="inline-flex items-center gap-1.5 text-xs">
      <span className={cn('h-2 w-2 rounded-full', active ? 'bg-success' : 'bg-[#94A3B8]')} />
      <span className={active ? 'text-success' : 'text-[var(--text-secondary)]'}>{active ? t('clients.statusFilter.active') : t('clients.statusFilter.inactive')}</span>
    </span>
  );
}
