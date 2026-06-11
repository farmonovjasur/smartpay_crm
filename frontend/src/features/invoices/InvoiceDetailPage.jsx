import { useState } from 'react';
import { Link, useParams, useNavigate } from '@tanstack/react-router';
import { ArrowLeft, Download, Trash2, FileText, Calendar, User, Hash } from 'lucide-react';
import { ErrorState, LoadingState, RoleGate, ConfirmDialog } from '@/components/common';
import { useInvoice, useDeleteInvoice } from './hooks';
import { downloadFile } from '@/lib/download';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';
import { showSuccess } from '@/lib/toast';
import { cn } from '@/lib/utils';

export default function InvoiceDetailPage() {
  const { id } = useParams({ from: '/invoices/$id' });
  const navigate = useNavigate();
  const { data: invoice, isLoading, isError, refetch } = useInvoice(id);
  const deleteInvoice = useDeleteInvoice();

  const [downloading, setDownloading] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);

  if (isLoading) return <LoadingState />;
  if (isError || !invoice) return <ErrorState onRetry={refetch} />;

  async function handleDownload() {
    setDownloading(true);
    try {
      await downloadFile(
        `/invoices/${invoice.id}/download`,
        undefined,
        `${invoice.invoice_number || `faktura-${invoice.id}`}.xlsx`
      );
    } finally {
      setDownloading(false);
    }
  }

  function confirmDelete() {
    deleteInvoice.mutate(invoice.id, {
      onSuccess: () => {
        showSuccess("Faktura o'chirildi");
        setDeleteOpen(false);
        navigate({ to: '/invoices' });
      },
    });
  }

  const items = Array.isArray(invoice.items) ? invoice.items : [];

  return (
    <div className="space-y-6">
      {/* Top bar */}
      <div className="flex items-center justify-between">
        <Link
          to="/invoices"
          className="inline-flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
        >
          <ArrowLeft className="h-4 w-4" />
          Fakturalar ro'yxatiga qaytish
        </Link>

        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={handleDownload}
            disabled={downloading}
            className="flex items-center gap-2 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-4 py-2.5 text-sm font-medium text-[var(--text-primary)] transition-colors hover:bg-bg-light disabled:opacity-60"
          >
            <Download className="h-4 w-4" />
            {downloading ? 'Yuklanmoqda…' : 'Excel yuklab olish'}
          </button>
          <RoleGate roles="admin">
            <button
              type="button"
              onClick={() => setDeleteOpen(true)}
              className="flex items-center gap-2 rounded-btn border border-danger bg-danger-bg px-4 py-2.5 text-sm font-medium text-danger-text transition-colors hover:bg-danger/10"
            >
              <Trash2 className="h-4 w-4" />
              O'chirish
            </button>
          </RoleGate>
        </div>
      </div>

      {/* Hero card */}
      <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-6">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-primary-bg">
              <FileText className="h-6 w-6 text-primary" />
            </div>
            <div className="space-y-2">
              <h1 className="text-xl font-bold leading-tight text-[var(--text-primary)]">
                {invoice.invoice_number}
              </h1>
              <div className="flex flex-wrap items-center gap-3 text-xs text-[var(--text-secondary)]">
                <InfoChip icon={Calendar} label={`Davr: ${formatPeriod(invoice.period)}`} />
                <InfoChip icon={Calendar} label={`Yaratilgan: ${formatDate(invoice.issue_date)}`} />
                {invoice.responsible_name && (
                  <InfoChip icon={User} label={`Mas'ul: ${invoice.responsible_name}`} />
                )}
              </div>
            </div>
          </div>

          <div className="space-y-1 text-right">
            <span className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
              Umumiy summa
            </span>
            <p className="text-3xl font-bold text-[var(--text-primary)]">
              {formatMoney(invoice.total_amount)}{' '}
              <span className="text-base font-medium text-[var(--text-secondary)]">so'm</span>
            </p>
            <p className="text-xs text-[var(--text-secondary)]">
              {invoice.items_count ?? items.length} ta mijoz
            </p>
          </div>
        </div>
      </div>

      {/* Snapshot info (R14.1) */}
      {(invoice.unit_price_snapshot || invoice.product_name_snapshot) && (
        <section className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {invoice.product_name_snapshot && (
            <SnapshotCard label="Mahsulot nomi (snapshot)" value={invoice.product_name_snapshot} />
          )}
          {invoice.unit_price_snapshot && (
            <SnapshotCard
              label="Birlik narxi (snapshot)"
              value={`${formatMoney(invoice.unit_price_snapshot)} so'm`}
              mono
            />
          )}
        </section>
      )}

      {/* Items table */}
      <section className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <header className="flex items-center justify-between border-b border-[var(--border)] px-5 py-4">
          <h2 className="text-sm font-semibold text-[var(--text-primary)]">
            Faktura tarkibi ({items.length})
          </h2>
        </header>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-5 py-3">#</th>
                <th className="px-3 py-3">Mijoz nomi</th>
                <th className="px-3 py-3">INN</th>
                <th className="px-3 py-3 text-right">Soni</th>
                <th className="px-3 py-3 text-right">Birlik narxi</th>
                <th className="px-3 py-3 text-right">Jami</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-[var(--text-secondary)]">
                    Hech qanday element yo'q
                  </td>
                </tr>
              ) : (
                items.map((item, i) => (
                  <tr
                    key={item.id}
                    className={cn(
                      'border-b border-[var(--border)] last:border-0',
                      i % 2 === 1 && 'bg-bg-light'
                    )}
                  >
                    <td className="px-5 py-3 text-[var(--text-secondary)]">{i + 1}</td>
                    <td className="px-3 py-3">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-[var(--text-primary)]">{item.client_name}</span>
                        {item.is_carried_debt && (
                          <span className="inline-flex items-center rounded-xl bg-warning-bg px-2 py-0.5 text-[10px] font-semibold text-warning-text">
                            Qarz qoldigi
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-3 py-3 font-mono text-[var(--text-secondary)]">
                      <span className="inline-flex items-center gap-1">
                        <Hash className="h-3 w-3" />
                        {item.client_inn}
                      </span>
                    </td>
                    <td className="px-3 py-3 text-right tabular-nums text-[var(--text-primary)]">
                      {item.quantity}
                    </td>
                    <td className="px-3 py-3 text-right tabular-nums text-[var(--text-primary)]">
                      {formatMoney(item.unit_price)}
                    </td>
                    <td className="px-3 py-3 text-right font-semibold tabular-nums text-[var(--text-primary)]">
                      {formatMoney(item.total_price)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
            {items.length > 0 && (
              <tfoot>
                <tr className="border-t-2 border-[var(--border)] bg-bg-light text-sm">
                  <td colSpan={5} className="px-5 py-3 text-right font-semibold text-[var(--text-secondary)]">
                    Jami:
                  </td>
                  <td className="px-3 py-3 text-right text-base font-bold tabular-nums text-[var(--text-primary)]">
                    {formatMoney(invoice.total_amount)} so'm
                  </td>
                </tr>
              </tfoot>
            )}
          </table>
        </div>
      </section>

      <ConfirmDialog
        open={deleteOpen}
        onOpenChange={setDeleteOpen}
        title="Fakturani o'chirish"
        description={`${invoice.invoice_number} (${formatPeriod(invoice.period)}) fakturasini o'chirmoqchimisiz? Bu amalni qaytarib bo'lmaydi.`}
        confirmLabel="Tasdiqlash"
        loading={deleteInvoice.isPending}
        onConfirm={confirmDelete}
      />
    </div>
  );
}

function InfoChip({ icon: Icon, label }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-xl bg-bg-light px-2.5 py-1">
      <Icon className="h-3.5 w-3.5" />
      {label}
    </span>
  );
}

function SnapshotCard({ label, value, mono }) {
  return (
    <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-4">
      <p className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">{label}</p>
      <p
        className={cn(
          'mt-1 text-base font-semibold text-[var(--text-primary)]',
          mono && 'tabular-nums'
        )}
      >
        {value}
      </p>
    </div>
  );
}
