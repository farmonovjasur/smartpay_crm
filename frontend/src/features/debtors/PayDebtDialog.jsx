import { useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, CreditCard, Banknote } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { formatMoney } from '@/lib/money';
import { usePayDebt } from './hooks';
import { cn } from '@/lib/utils';

const METHOD_OPTIONS = [
  {
    value: 'fakt',
    icon: CreditCard,
    label: 'Fakt (online)',
    desc: 'Hisobga ko\'chirish',
    iconBg: 'bg-primary-bg',
    iconColor: 'text-primary',
  },
  {
    value: 'naqt',
    icon: Banknote,
    label: 'Naqt',
    desc: 'Naqd pul orqali',
    iconBg: 'bg-teal-bg',
    iconColor: 'text-teal',
  },
];

/**
 * Qarzni to'liq to'lash dialogi.
 * R9.2/R9.3: faqat `method` so'raladi, summa maydoni YO'Q.
 *
 * @param {{
 *   open: boolean,
 *   onOpenChange: (v: boolean) => void,
 *   debt: { id: string|number, client_name: string, amount: string, payment_type_snapshot: string },
 * }} props
 */
export function PayDebtDialog({ open, onOpenChange, debt }) {
  const mutation = usePayDebt(debt?.id);

  // Default method — qarz `payment_type_snapshot` ga qarab.
  const defaultMethod = debt?.payment_type_snapshot === 'naqt' ? 'naqt' : 'fakt';

  const {
    handleSubmit,
    reset,
    control,
    formState: { isSubmitting },
  } = useForm({ defaultValues: { method: defaultMethod } });

  useEffect(() => {
    if (open) reset({ method: defaultMethod });
  }, [open, defaultMethod, reset]);

  function onSubmit(data) {
    mutation.mutate(
      { method: data.method },
      {
        onSuccess: () => {
          // R9.4: muvaffaqiyat Toast'i.
          showSuccess(`Qarz to'liq to'landi (${data.method === 'fakt' ? 'Fakt' : 'Naqt'})`);
          onOpenChange(false);
        },
        onError: (err) => {
          const status = err?.response?.status;
          // R9.5: 409 → "Bu qarz allaqachon to'langan" + dialog yopiladi.
          if (status === 409) {
            handleMutationError(err, {
              statusMessages: { 409: "Bu qarz allaqachon to'langan" },
            });
            onOpenChange(false);
            return;
          }
          handleMutationError(err);
        },
      }
    );
  }

  const submitting = mutation.isPending || isSubmitting;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[480px] p-0">
        <form onSubmit={handleSubmit(onSubmit)}>
          {/* Header */}
          <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
            <div className="space-y-1">
              <h2 className="text-xl font-semibold text-[var(--text-primary)]">Qarzni to'lash</h2>
              <p className="text-sm text-[var(--text-secondary)]">
                {debt?.client_name && (
                  <>
                    <span className="font-medium text-[var(--text-primary)]">{debt.client_name}</span>{' '}
                  </>
                )}
                — to'lov usulini tanlang
              </p>
            </div>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="flex h-8 w-8 items-center justify-center rounded-md bg-bg-light text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
              aria-label="Yopish"
            >
              <X className="h-[18px] w-[18px]" />
            </button>
          </div>

          {/* Body */}
          <div className="space-y-5 p-6">
            {/* Amount summary (read-only — R9.3: kiritish maydoni YO'Q) */}
            <div className="rounded-card border border-[var(--border)] bg-bg-light p-4">
              <p className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
                To'lanadigan summa
              </p>
              <p className="mt-1 text-2xl font-bold text-[var(--text-primary)]">
                {formatMoney(debt?.amount)}{' '}
                <span className="text-sm font-medium text-[var(--text-secondary)]">so'm</span>
              </p>
              <p className="mt-1 text-[11px] text-[var(--text-secondary)]">
                Qarz to'liq yopiladi (qisman to'lov qabul qilinmaydi)
              </p>
            </div>

            {/* Method */}
            <div className="space-y-3">
              <label className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
                To'lov usuli <span className="text-danger">*</span>
              </label>
              <Controller
                control={control}
                name="method"
                rules={{ required: true }}
                render={({ field }) => (
                  <div className="grid grid-cols-2 gap-3">
                    {METHOD_OPTIONS.map((opt) => {
                      const selected = field.value === opt.value;
                      return (
                        <button
                          type="button"
                          key={opt.value}
                          onClick={() => field.onChange(opt.value)}
                          aria-pressed={selected}
                          className={cn(
                            'flex flex-col items-start gap-2 rounded-btn p-4 text-left transition-colors',
                            selected
                              ? 'border-2 border-primary bg-primary-bg'
                              : 'border border-[var(--border)] bg-[var(--card-bg)] hover:border-[var(--border-dark)]'
                          )}
                        >
                          <span className={cn('flex h-8 w-8 items-center justify-center rounded-md', opt.iconBg)}>
                            <opt.icon className={cn('h-4 w-4', opt.iconColor)} />
                          </span>
                          <span
                            className={cn(
                              'text-sm font-semibold',
                              selected ? 'text-primary' : 'text-[var(--text-primary)]'
                            )}
                          >
                            {opt.label}
                          </span>
                          <span
                            className={cn(
                              'text-[11px] leading-tight',
                              selected ? 'text-primary' : 'text-[var(--text-secondary)]'
                            )}
                          >
                            {opt.desc}
                          </span>
                        </button>
                      );
                    })}
                  </div>
                )}
              />
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
              disabled={submitting}
              className="rounded-btn bg-success px-5 py-2.5 text-sm font-medium text-white transition-colors hover:opacity-90 disabled:opacity-60"
            >
              {submitting ? "To'lanmoqda…" : "To'lashni tasdiqlash"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
