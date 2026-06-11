import { useMemo, useState } from 'react';
import { Link } from '@tanstack/react-router';
import { Plus, FileText, Download, Trash2 } from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState, RoleGate, ConfirmDialog,
} from '@/components/common';
import { useInvoices, useDeleteInvoice } from './hooks';
import { GenerateInvoiceDialog } from './GenerateInvoiceDialog';
import { downloadFile } from '@/lib/download';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';
import { showSuccess } from '@/lib/toast';
import { cn } from '@/lib/utils';

export default function InvoicesPage() {
  const [page, setPage] = useState(1);
  const [generateOpen, setGenerateOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [downloadingId, setDownloadingId] = useState(null);

  const filters = useMemo(() => ({ page }), [page]);
  const { data, isLoading, isError, refetch } = useInvoices(filters);
  const deleteInvoice = useDeleteInvoice();

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;

  async function handleDownload(invoice) {
    setDownloadingId(invoice.id);
    try {
      await downloadFile(
        `/invoices/${invoice.id}/download`,
        undefined,
        `${invoice.invoice_number || `faktura-${invoice.id}`}.xlsx`
      );
    } finally {
      setDownloadingId(null);
    }
  }

  function confirmDelete() {
    if (!deleteTarget) return;
    deleteInvoice.mutate(deleteTarget.id, {
      onSuccess: () => {
        showSuccess("Faktura o'chirildi");
        setDeleteTarget(null);
      },
    });
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Hisob-fakturalar"
        count={total || undefined}
        actions={
          <button
            type="button"
            onClick={() => setGenerateOpen(true)}
            className="flex items-center gap-2 rounded-btn bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
          >
            <Plus className="h-4 w-4" />
            Yangi faktura yaratish
          </button>
        }
      />

      {isLoading ? (
        <CardsGridSkeleton />
      ) : rows.length === 0 ? (
        <EmptyState
          title="Hozircha faktura yo'q"
          description="Yangi faktura yaratish uchun yuqoridagi tugmadan foydalaning"
        />
      ) : (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {rows.map((inv) => (
            <InvoiceCard
              key={inv.id}
              invoice={inv}
              downloading={downloadingId === inv.id}
              onDownload={() => handleDownload(inv)}
              onDelete={() => setDeleteTarget(inv)}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {data && total > 0 && (
        <div className="flex items-center justify-between">
          <span className="text-[13px] text-[var(--text-secondary)]">
            Jami: {total} ta
          </span>
          <Pagination page={data.page} total={total} pageSize={pageSize} onPageChange={setPage} />
        </div>
      )}

      <GenerateInvoiceDialog open={generateOpen} onOpenChange={setGenerateOpen} />
      <ConfirmDialog
        open={!!deleteTarget}
        onOpenChange={(v) => !v && setDeleteTarget(null)}
        title="Fakturani o'chirish"
        description={
          deleteTarget
            ? `${deleteTarget.invoice_number} (${formatPeriod(deleteTarget.period)}) fakturasini o'chirmoqchimisiz? Bu amalni qaytarib bo'lmaydi.`
            : ''
        }
        confirmLabel="Tasdiqlash"
        loading={deleteInvoice.isPending}
        onConfirm={confirmDelete}
      />
    </div>
  );
}

/** Faktura kartochkasi — ui.pen FAKTURA-O=... shabloniga mos. */
function InvoiceCard({ invoice, downloading, onDownload, onDelete }) {
  return (
    <article
      className={cn(
        'flex flex-col gap-4 rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-5',
        'transition-shadow hover:shadow-md'
      )}
    >
      {/* Card header */}
      <header className="flex items-center gap-2.5">
        <FileText className="h-5 w-5 shrink-0 text-primary" />
        <Link
          to="/invoices/$id"
          params={{ id: String(invoice.id) }}
          className="truncate text-[13px] font-semibold text-[var(--text-primary)] hover:text-primary"
          title={invoice.invoice_number}
        >
          {invoice.invoice_number}
        </Link>
      </header>

      {/* Card body */}
      <div className="space-y-2 text-[13px]">
        <Row label="Davr:" value={formatPeriod(invoice.period)} />
        <Row label="Mijozlar soni:" value={`${invoice.items_count ?? 0} ta`} />
        <Row
          label="Umumiy summa:"
          value={`${formatMoney(invoice.total_amount)} so'm`}
        />
        {invoice.responsible_name ? (
          <Row label="Mas'ul:" value={invoice.responsible_name} />
        ) : null}
      </div>

      {/* Footer */}
      <footer className="text-xs text-[var(--text-secondary)]">
        Yaratilgan: {formatDate(invoice.issue_date)}
      </footer>

      {/* Actions */}
      <div className="flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={onDownload}
          disabled={downloading}
          className={cn(
            'flex items-center gap-1.5 rounded-btn border border-[var(--border)] bg-bg-light px-3 py-2 text-xs font-medium text-[var(--text-secondary)]',
            'transition-colors hover:bg-[var(--hover-bg)] disabled:opacity-60'
          )}
        >
          <Download className="h-3.5 w-3.5" />
          {downloading ? 'Yuklanmoqda…' : 'Yuklab olish'}
        </button>
        <RoleGate roles="admin">
          <button
            type="button"
            onClick={onDelete}
            className="flex items-center gap-1.5 rounded-btn border border-danger bg-danger-bg px-3 py-2 text-xs font-medium text-danger-text transition-colors hover:bg-danger/10"
          >
            <Trash2 className="h-3.5 w-3.5" />
            O'chirish
          </button>
        </RoleGate>
      </div>
    </article>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-[var(--text-secondary)]">{label}</span>
      <span className="font-semibold text-[var(--text-primary)]">{value}</span>
    </div>
  );
}

function CardsGridSkeleton() {
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      {Array.from({ length: 4 }).map((_, i) => (
        <div
          key={i}
          className="h-48 animate-pulse rounded-card border border-[var(--border)] bg-[var(--card-bg)]"
        />
      ))}
    </div>
  );
}
