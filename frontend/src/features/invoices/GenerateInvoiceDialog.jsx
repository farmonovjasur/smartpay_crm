import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X, FileText } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { validators } from '@/lib/validation';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { formatMoney } from '@/lib/money';
import { useGenerateInvoice } from './hooks';
import { cn } from '@/lib/utils';

/** YYYY-MM joriy oy uchun (default qiymat). */
function currentPeriod() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * Yangi UMUMIY oylik fakturani yaratish dialogi.
 * R8: period (YYYY-MM regex) → POST /invoices/generate
 *
 * @param {{ open: boolean, onOpenChange: (v:boolean)=>void }} props
 */
export function GenerateInvoiceDialog({ open, onOpenChange }) {
  const mutation = useGenerateInvoice();

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm({
    defaultValues: { period: currentPeriod() },
  });

  useEffect(() => {
    if (open) reset({ period: currentPeriod() });
  }, [open, reset]);

  function onSubmit(data) {
    mutation.mutate(
      { period: data.period },
      {
        onSuccess: (res) => {
          // R8.4: 201 → invoice_number, total_amount, items_count Toast bilan ko'rsatiladi.
          const inv = res?.data ?? res ?? {};
          const num = inv.invoice_number ?? inv.invoiceNumber ?? '';
          const total = inv.total_amount ?? inv.totalAmount ?? '0';
          const itemsCount = inv.items_count ?? inv.itemsCount ?? 0;
          showSuccess(
            `Faktura ${num} yaratildi: ${itemsCount} ta mijoz, ${formatMoney(total)} so'm`
          );
          onOpenChange(false);
        },
        onError: (err) =>
          handleMutationError(err, {
            setError,
            fields: ['period'],
            conflictField: 'period',
            statusMessages: {
              // R8.5
              409: 'Bu davr uchun faktura allaqachon yaratilgan',
              // R8.6
              422: (e) => {
                // 422 maydon xatolari yo'q bo'lsa "mos mijoz topilmadi" ko'rsatamiz.
                if (e?.response?.data?.errors) return null;
                return 'Bu davr uchun mos mijoz topilmadi';
              },
            },
          }),
      }
    );
  }

  const validatePeriod = (v) => validators.period(v) || true;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[480px] p-0">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          {/* Header */}
          <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
            <div className="flex items-start gap-3">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary-bg">
                <FileText className="h-5 w-5 text-primary" />
              </span>
              <div className="space-y-1">
                <h2 className="text-xl font-semibold text-[var(--text-primary)]">
                  Yangi faktura yaratish
                </h2>
                <p className="text-sm text-[var(--text-secondary)]">
                  Tanlangan davr uchun barcha mos mijozlarni umumiy fakturaga jamlaydi
                </p>
              </div>
            </div>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-bg-light text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
              aria-label="Yopish"
            >
              <X className="h-[18px] w-[18px]" />
            </button>
          </div>

          {/* Body */}
          <div className="space-y-5 p-6">
            <div className="space-y-2">
              <label htmlFor="period" className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
                Davr (YYYY-MM) <span className="text-danger">*</span>
              </label>
              <input
                id="period"
                type="text"
                placeholder="2026-05"
                maxLength={7}
                aria-invalid={!!errors.period}
                {...register('period', { validate: validatePeriod })}
                className={cn(
                  'h-11 w-full rounded-btn border bg-[var(--card-bg)] px-4 text-sm outline-none focus:ring-2 focus:ring-primary',
                  errors.period ? 'border-danger' : 'border-[var(--border)]'
                )}
              />
              {errors.period ? (
                <p className="text-xs text-danger">{errors.period.message}</p>
              ) : (
                <p className="text-xs text-[var(--text-secondary)]">Format: 2026-05 (yil-oy)</p>
              )}
            </div>
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-3 border-t border-[var(--border)] p-6">
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="rounded-btn border border-[var(--border)] px-5 py-2.5 text-sm font-medium text-[var(--text-secondary)] hover:bg-bg-light"
            >
              Bekor qilish
            </button>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-60"
            >
              {mutation.isPending ? 'Yaratilmoqda…' : 'Faktura yaratish'}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
