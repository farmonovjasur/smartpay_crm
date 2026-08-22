import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { X, Wallet } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { usePayLedger } from './hooks';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

/**
 * Qarz to'lash dialogi — to'liq yoki qisman to'lov.
 * To'lov usuli (naqt/fakt) talab qilinmaydi.
 */
export function PayLedgerDialog({ open, onOpenChange, entry }) {
  const mutation = usePayLedger(entry?.id);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    setValue,
    formState: { errors },
  } = useForm({
    defaultValues: { amount: '' },
  });

  const remaining = entry ? parseFloat(entry.remaining_amount || entry.total_amount) - parseFloat(entry.paid_amount || 0) : 0;

  useEffect(() => {
    if (open && entry) {
      const rem = parseFloat(entry.remaining_amount ?? entry.total_amount ?? 0) - parseFloat(entry.paid_amount ?? 0);
      reset({ amount: '' });
    }
  }, [open, entry, reset]);

  function handlePayFull() {
    const rem = parseFloat(entry.remaining_amount ?? entry.total_amount ?? 0);
    setValue('amount', String(rem > 0 ? rem : remaining), { shouldValidate: true });
  }

  function onSubmit(data) {
    mutation.mutate(
      { amount: data.amount },
      {
        onSuccess: (res) => {
          showSuccess(res.message || "To'lov muvaffaqiyatli amalga oshirildi.");
          onOpenChange(false);
        },
        onError: (err) => handleMutationError(err, setError),
      }
    );
  }

  if (!entry) return null;

  const remainingAmount = parseFloat(entry.remaining_amount ?? '0');

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--border)] pb-4">
          <div>
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">
              Qarz to'lash
            </h2>
            <p className="text-xs text-[var(--text-secondary)]">
              {entry.client_name}
            </p>
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="rounded-lg p-1.5 text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Qarz xulosa */}
        <div className="mt-4 rounded-lg border border-[var(--border)] bg-[var(--hover-bg)] p-4">
          <div className="flex justify-between text-sm">
            <span className="text-[var(--text-secondary)]">Jami qarz:</span>
            <span className="font-semibold text-[var(--text-primary)]">
              {formatMoney(entry.total_amount)} so'm
            </span>
          </div>
          {parseFloat(entry.paid_amount) > 0 && (
            <div className="mt-1 flex justify-between text-sm">
              <span className="text-[var(--text-secondary)]">To'langan:</span>
              <span className="font-semibold text-success">{formatMoney(entry.paid_amount)} so'm</span>
            </div>
          )}
          <div className="mt-2 flex justify-between border-t border-[var(--border)] pt-2 text-sm">
            <span className="font-medium text-[var(--text-primary)]">Qoldiq:</span>
            <span className="text-lg font-bold text-danger-text">
              {formatMoney(entry.remaining_amount)} so'm
            </span>
          </div>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-4 space-y-4">
          {/* Summa */}
          <div>
            <label className="mb-1 block text-sm font-medium text-[var(--text-primary)]">
              To'lov summasi (so'm)
            </label>
            <div className="flex gap-2">
              <input
                {...register('amount', {
                  required: "To'lov summasi kiritilishi shart",
                  validate: (v) => {
                    const num = parseFloat(v);
                    if (isNaN(num) || num <= 0) return 'Musbat son kiriting';
                    if (num > remainingAmount) return `Maksimal: ${formatMoney(remainingAmount)} so'm`;
                    return true;
                  },
                })}
                type="number"
                min="0"
                max={remainingAmount}
                step="any"
                placeholder="Summani kiriting"
                autoFocus
                className={cn(
                  'h-11 flex-1 rounded-btn border bg-[var(--card-bg)] px-4 text-sm tabular-nums outline-none placeholder:text-[var(--text-secondary)] focus:ring-2 focus:ring-primary',
                  errors.amount ? 'border-danger' : 'border-[var(--border)]'
                )}
              />
              <button
                type="button"
                onClick={handlePayFull}
                className="shrink-0 rounded-btn border border-primary bg-primary-bg px-3 py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary hover:text-white"
              >
                To'liq
              </button>
            </div>
            {errors.amount && (
              <p className="mt-1 text-xs text-danger">{errors.amount.message}</p>
            )}
          </div>

          {/* Tugmalar */}
          <div className="flex items-center justify-end gap-3 border-t border-[var(--border)] pt-4">
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="rounded-btn border border-[var(--border)] px-4 py-2.5 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)]"
            >
              Bekor qilish
            </button>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="flex items-center gap-2 rounded-btn bg-success px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:opacity-60"
            >
              <Wallet className="h-4 w-4" />
              {mutation.isPending ? "To'lanmoqda..." : "To'lash"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
