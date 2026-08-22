import { useState, useEffect } from 'react';
import { Link, useParams, useRouter } from '@tanstack/react-router';
import {
  ArrowLeft, Pencil, Trash2, Wallet, Building2, Hash, Phone,
  FileText, Calendar, User, Save, Plus, X, Trash, Printer
} from 'lucide-react';
import { useForm, useFieldArray } from 'react-hook-form';
import { ErrorState, LoadingState } from '@/components/common';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { useLedgerEntry, useUpdateLedger, useDeleteLedger } from './hooks';
import { PayLedgerDialog } from './PayLedgerDialog';
import { LedgerReceipt } from './LedgerReceipt';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { formatMoney } from '@/lib/money';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';

export default function LedgerDetailPage() {
  const { id } = useParams({ strict: false });
  const router = useRouter();
  const { data: entry, isLoading, isError, refetch } = useLedgerEntry(id);
  const [payOpen, setPayOpen] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState(false);
  const [receiptOpen, setReceiptOpen] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState(null);
  const deleteMutation = useDeleteLedger();

  if (isLoading) return <LoadingState />;
  if (isError || !entry) return <ErrorState onRetry={refetch} />;

  const isActive = entry.status === 'active';
  const isPartial = entry.status === 'partial';
  const isPaid = entry.status === 'paid';
  const canEdit = isActive || isPartial;
  const canDelete = isActive;
  const canPay = isActive || isPartial;

  function handleDelete() {
    deleteMutation.mutate(entry.id, {
      onSuccess: () => {
        showSuccess("Qarz o'chirildi.");
        router.history.back();
      },
    });
  }

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      {/* Top bar */}
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => router.history.back()}
          className="inline-flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)]"
        >
          <ArrowLeft className="h-4 w-4" />
          Orqaga
        </button>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => {
              setSelectedPayment(null);
              setReceiptOpen(true);
            }}
            className="flex items-center gap-2 rounded-btn border border-[var(--border)] bg-white px-4 py-2 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)] shadow-sm"
          >
            <Printer className="h-4 w-4" />
            Umumiy chek
          </button>
          
          {canPay && (
            <button
              type="button"
              onClick={() => setPayOpen(true)}
              className="flex items-center gap-2 rounded-btn bg-success px-4 py-2 text-sm font-semibold text-white transition-colors hover:opacity-90"
            >
              <Wallet className="h-4 w-4" />
              To'lash
            </button>
          )}
          {canEdit && (
            <button
              type="button"
              onClick={() => setEditOpen(true)}
              className="flex items-center gap-2 rounded-btn border border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)]"
            >
              <Pencil className="h-4 w-4" />
              Tahrirlash
            </button>
          )}
          {canDelete && (
            <button
              type="button"
              onClick={() => setDeleteConfirm(true)}
              className="flex items-center gap-2 rounded-btn border border-danger px-4 py-2 text-sm font-medium text-danger-text transition-colors hover:bg-danger-bg"
            >
              <Trash2 className="h-4 w-4" />
              O'chirish
            </button>
          )}
        </div>
      </div>

      {/* Hero card: ism + INN + aloqa + status */}
      <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-2xl font-bold text-white shadow-sm">
              {entry.client_name?.[0]?.toUpperCase() || 'M'}
            </div>
            <div className="space-y-1.5">
              <h2 className="text-2xl font-bold leading-tight text-[var(--text-primary)]">{entry.client_name}</h2>
              <div className="flex flex-wrap items-center gap-3 text-sm text-[var(--text-secondary)]">
              <span className="inline-flex items-center gap-1.5">
                <Hash className="h-3.5 w-3.5" />
                INN: {entry.client_inn}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <Phone className="h-3.5 w-3.5" />
                {entry.client_phone}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <Calendar className="h-3.5 w-3.5" />
                {formatDate(entry.created_at)}
              </span>
              {entry.created_by_name && (
                <span className="inline-flex items-center gap-1.5">
                  <User className="h-3.5 w-3.5" />
                  Yaratdi: <span className="font-medium text-[var(--text-primary)]">{entry.created_by_name}</span>
                </span>
              )}
            </div>
          </div>
          </div>
          <StatusBadge status={entry.status} />
        </div>

        {/* Summa xulosa */}
        <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
          <SummaryCard 
            label="Jami qarz" 
            value={entry.total_amount} 
            color="text-[var(--text-primary)]" 
            bgClass="bg-[var(--hover-bg)] border-[var(--border)]"
          />
          <SummaryCard 
            label="To'langan" 
            value={entry.paid_amount} 
            color="text-success" 
            bgClass="bg-success-bg/30 border-success/20"
          />
          <SummaryCard 
            label="Qoldiq" 
            value={entry.remaining_amount} 
            color="text-danger-text" 
            bgClass="bg-danger-bg/30 border-danger/20"
          />
        </div>
      </div>

      {/* Xizmat qatorlari */}
      <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <div className="border-b border-[var(--border)] px-6 py-4">
          <h3 className="text-base font-semibold text-[var(--text-primary)]">
            Xizmatlar ({entry.items_count} ta)
          </h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-6 py-3">#</th>
                <th className="px-3 py-3">Xizmat izoh</th>
                <th className="px-3 py-3">Sana</th>
                <th className="px-6 py-3 text-right">Summa</th>
              </tr>
            </thead>
            <tbody>
              {(entry.items || []).map((item, i) => (
                <tr
                  key={item.id}
                  className="border-b border-[var(--border)] last:border-0"
                >
                  <td className="px-6 py-3 text-[var(--text-secondary)]">{i + 1}</td>
                  <td className="px-3 py-3 text-[var(--text-primary)]">
                    <span className="inline-flex items-center gap-2">
                      <FileText className="h-4 w-4 text-[var(--text-secondary)]" />
                      {item.description}
                    </span>
                  </td>
                  <td className="px-3 py-3 text-[var(--text-secondary)]">
                    {formatDate(item.created_at)}
                  </td>
                  <td className="px-6 py-3 text-right font-semibold tabular-nums text-[var(--text-primary)]">
                    {formatMoney(item.amount)} <span className="text-xs text-[var(--text-secondary)]">so'm</span>
                  </td>
                </tr>
              ))}
              <tr className="bg-bg-light">
                <td />
                <td colSpan={2} className="px-3 py-3 text-right text-sm font-semibold text-[var(--text-primary)]">
                  Jami:
                </td>
                <td className="px-6 py-3 text-right text-base font-bold tabular-nums text-[var(--text-primary)]">
                  {formatMoney(entry.total_amount)} <span className="text-xs text-[var(--text-secondary)]">so'm</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {/* Izoh */}
      {entry.notes && (
        <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
          <h3 className="mb-2 text-sm font-semibold text-[var(--text-primary)]">Izoh</h3>
          <p className="text-sm text-[var(--text-secondary)]">{entry.notes}</p>
        </div>
      )}

      {/* To'lovlar tarixi */}
      {entry.payments && entry.payments.length > 0 ? (
        <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
          <div className="border-b border-[var(--border)] px-6 py-4">
            <h3 className="text-sm font-semibold text-[var(--text-primary)]">To'lovlar tarixi</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                  <th className="w-12 px-6 py-3">#</th>
                  <th className="px-3 py-3">To'lov sanasi</th>
                  <th className="px-3 py-3 text-right">Summa</th>
                  <th className="px-3 py-3">Qabul qildi</th>
                  <th className="px-6 py-3 text-right">Amal</th>
                </tr>
              </thead>
              <tbody>
                {entry.payments.map((payment, i) => (
                  <tr key={payment.id} className="border-b border-[var(--border)] last:border-0 hover:bg-bg-light">
                    <td className="px-6 py-3 text-[var(--text-secondary)]">{i + 1}</td>
                    <td className="px-3 py-3 text-[var(--text-primary)]">{formatDate(payment.created_at)}</td>
                    <td className="px-3 py-3 text-right font-semibold tabular-nums text-success">
                      {formatMoney(payment.amount)} <span className="text-xs">so'm</span>
                    </td>
                    <td className="px-3 py-3 text-[var(--text-secondary)]">{payment.created_by || 'Tizim'}</td>
                    <td className="px-6 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => {
                          setSelectedPayment(payment);
                          setReceiptOpen(true);
                        }}
                        className="inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
                      >
                        <Printer className="h-3.5 w-3.5" />
                        Chek
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        /* Agar payments collection yo'q bo'lsa (eski yozuvlar uchun fallback) */
        (isPaid || isPartial) && entry.paid_at && (
          <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-6 shadow-sm">
            <h3 className="mb-3 text-sm font-semibold text-[var(--text-primary)]">
              To'lov ma'lumotlari
            </h3>
            <div className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <span className="text-[var(--text-secondary)]">To'langan summa:</span>
                <p className="font-semibold text-success">{formatMoney(entry.paid_amount)} so'm</p>
              </div>
              <div>
                <span className="text-[var(--text-secondary)]">To'lov sanasi:</span>
                <p className="font-medium text-[var(--text-primary)]">{formatDate(entry.paid_at)}</p>
              </div>
              {entry.paid_by_name && (
                <div>
                  <span className="text-[var(--text-secondary)]">To'lovni qabul qilgan:</span>
                  <p className="font-medium text-[var(--text-primary)]">{entry.paid_by_name}</p>
                </div>
              )}
            </div>
          </div>
        )
      )}

      {/* Dialogs */}
      <PayLedgerDialog
        open={payOpen}
        onOpenChange={setPayOpen}
        entry={entry}
      />

      <EditLedgerDialog
        open={editOpen}
        onOpenChange={setEditOpen}
        entry={entry}
      />

      <LedgerReceipt
        open={receiptOpen}
        onOpenChange={setReceiptOpen}
        entry={entry}
        payment={selectedPayment}
      />

      {/* Delete confirmation */}
      <Dialog open={deleteConfirm} onOpenChange={setDeleteConfirm}>
        <DialogContent className="max-w-sm">
          <h3 className="text-lg font-semibold text-[var(--text-primary)]">Qarzni o'chirish</h3>
          <p className="mt-2 text-sm text-[var(--text-secondary)]">
            Haqiqatan ham bu qarz yozuvini o'chirmoqchimisiz? Bu amalni ortga qaytarib bo'lmaydi.
          </p>
          <div className="mt-4 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={() => setDeleteConfirm(false)}
              className="rounded-btn border border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text-secondary)]"
            >
              Bekor qilish
            </button>
            <button
              type="button"
              onClick={handleDelete}
              disabled={deleteMutation.isPending}
              className="flex items-center gap-2 rounded-btn bg-danger px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
            >
              <Trash2 className="h-4 w-4" />
              {deleteMutation.isPending ? "O'chirilmoqda..." : "O'chirish"}
            </button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

/** Tahrirlash dialogi — xizmat qatorlarini yangilash uchun */
function EditLedgerDialog({ open, onOpenChange, entry }) {
  const mutation = useUpdateLedger(entry?.id);
  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    watch,
    formState: { errors },
  } = useForm({
    defaultValues: {
      notes: entry?.notes || '',
      items: entry?.items?.map((i) => ({
        description: i.description,
        amount: String(i.amount),
      })) || [{ description: '', amount: '' }],
    },
  });

  const { fields, append, remove: removeField } = useFieldArray({
    control,
    name: 'items',
  });

  const watchedItems = watch('items');
  const total = (watchedItems || []).reduce((s, i) => s + (parseFloat(i?.amount) || 0), 0);

  // Reset form when dialog opens with fresh entry data
  useEffect(() => {
    if (open && entry) {
      reset({
        notes: entry.notes || '',
        items: entry.items?.map((i) => ({
          description: i.description,
          amount: String(i.amount),
        })) || [{ description: '', amount: '' }],
      });
    }
  }, [open, entry, reset]);

  function onSubmit(data) {
    const validItems = data.items.filter(
      (item) => item.description.trim() !== '' && parseFloat(item.amount) > 0
    );

    if (validItems.length === 0) {
      setError('items', { message: 'Kamida bitta xizmat qatori kiritilishi shart.' });
      return;
    }

    mutation.mutate(
      {
        notes: data.notes || null,
        items: validItems.map((i) => ({
          description: i.description.trim(),
          amount: String(i.amount),
        })),
      },
      {
        onSuccess: () => {
          showSuccess('Qarz muvaffaqiyatli yangilandi.');
          onOpenChange(false);
        },
        onError: (err) => handleMutationError(err, setError),
      }
    );
  }

  if (!entry) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <div className="flex items-center justify-between border-b border-[var(--border)] pb-4">
          <h2 className="text-lg font-semibold text-[var(--text-primary)]">Qarzni tahrirlash</h2>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-5">
          <div>
            <label className="mb-2 block text-sm font-medium text-[var(--text-primary)]">
              Xizmatlar
            </label>
            <div className="space-y-2">
              {fields.map((field, index) => (
                <div key={field.id} className="flex items-start gap-2">
                  <input
                    {...register(`items.${index}.description`, { required: true })}
                    placeholder="Xizmat turi"
                    className="h-10 flex-1 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                  />
                  <input
                    {...register(`items.${index}.amount`, { required: true })}
                    type="number"
                    min="0"
                    step="any"
                    placeholder="Summa"
                    className="h-10 w-40 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm tabular-nums outline-none focus:ring-2 focus:ring-primary"
                  />
                  {fields.length > 1 && (
                    <button
                      type="button"
                      onClick={() => removeField(index)}
                      className="flex h-10 w-10 shrink-0 items-center justify-center rounded-btn border border-[var(--border)] text-[var(--text-secondary)] hover:border-danger hover:text-danger-text"
                    >
                      <Trash className="h-4 w-4" />
                    </button>
                  )}
                </div>
              ))}
            </div>
            <button
              type="button"
              onClick={() => append({ description: '', amount: '' })}
              className="mt-2 flex items-center gap-1.5 rounded-btn border border-dashed border-[var(--border)] px-3 py-2 text-xs font-medium text-[var(--text-secondary)] hover:border-primary hover:text-primary"
            >
              <Plus className="h-3.5 w-3.5" />
              Qo'shish
            </button>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--text-primary)]">
              Umumiy izoh
            </label>
            <textarea
              {...register('notes')}
              rows={2}
              className="w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
            />
          </div>

          <div className="flex items-center justify-between border-t border-[var(--border)] pt-4">
            <div>
              <span className="text-sm text-[var(--text-secondary)]">Jami: </span>
              <span className="text-lg font-bold">{formatMoney(total)}</span>
              <span className="ml-1 text-sm text-[var(--text-secondary)]">so'm</span>
            </div>
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => onOpenChange(false)}
                className="rounded-btn border border-[var(--border)] px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)]"
              >
                Bekor qilish
              </button>
              <button
                type="submit"
                disabled={mutation.isPending}
                className="flex items-center gap-2 rounded-btn bg-primary px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
              >
                <Save className="h-4 w-4" />
                {mutation.isPending ? 'Saqlanmoqda...' : 'Saqlash'}
              </button>
            </div>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function StatusBadge({ status }) {
  const map = {
    paid: { cls: 'bg-success-bg text-success-text', label: "To'langan" },
    partial: { cls: 'bg-warning-bg text-warning-text', label: 'Qisman' },
    active: { cls: 'bg-danger-bg text-danger-text', label: 'Faol qarz' },
  };
  const s = map[status] || map.active;
  return (
    <span className={cn('inline-flex items-center rounded-xl px-3 py-1 text-xs font-semibold', s.cls)}>
      {s.label}
    </span>
  );
}

function SummaryCard({ label, value, color, bgClass }) {
  return (
    <div className={cn("rounded-xl border p-5 shadow-sm", bgClass)}>
      <p className="text-sm font-medium text-[var(--text-secondary)]">{label}</p>
      <p className={cn('mt-2 text-2xl font-bold tracking-tight', color)}>
        {formatMoney(value)} <span className="text-sm font-medium opacity-70 text-[var(--text-secondary)]">so'm</span>
      </p>
    </div>
  );
}
