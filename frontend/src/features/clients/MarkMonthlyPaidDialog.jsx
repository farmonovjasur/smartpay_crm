import { useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, CreditCard, Banknote, AlertTriangle } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { validators } from '@/lib/validation';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { useMarkMonthlyPaid } from './hooks';
import { cn } from '@/lib/utils';

const METHOD_OPTIONS = [
  {
    value: 'naqt',
    icon: Banknote,
    label: 'Naqt',
    desc: 'Naqd pul orqali',
    iconBg: 'bg-teal-bg',
    iconColor: 'text-teal',
  },
  {
    value: 'fakt',
    icon: CreditCard,
    label: 'Fakt (online)',
    desc: 'Hisobga ko\'chirish',
    iconBg: 'bg-primary-bg',
    iconColor: 'text-primary',
  },
];

/** YYYY-MM joriy oy uchun (default qiymat). */
function currentPeriod() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * Oylik to'lovni qo'lda qayd qilish dialogi.
 * R10: period (YYYY-MM regex) + method (fakt/naqt).
 *
 * @param {{
 *   open: boolean,
 *   onOpenChange: (v: boolean) => void,
 *   client: { id: string|number, name: string, payment_type: 'fakt'|'naqt'|'qarz' },
 * }} props
 */
export function MarkMonthlyPaidDialog({ open, onOpenChange, client }) {
  const mutation = useMarkMonthlyPaid(client?.id);

  // R10.5: `fakt` mijozlari uchun bu amal birlamchi taklif qilinmaydi.
  // Default `method` mijozning to'lov turiga qarab tanlanadi (qarz/naqt → naqt, fakt → fakt).
  const defaultMethod = client?.payment_type === 'fakt' ? 'fakt' : 'naqt';

  const {
    register,
    handleSubmit,
    reset,
    setError,
    control,
    formState: { errors },
  } = useForm({
    defaultValues: { period: currentPeriod(), method: defaultMethod },
  });

  useEffect(() => {
    if (open) {
      reset({ period: currentPeriod(), method: defaultMethod });
    }
  }, [open, defaultMethod, reset]);

  function onSubmit(data) {
    mutation.mutate(
      { period: data.period, method: data.method },
      {
        onSuccess: (res) => {
          // R10.3: qaytgan `status` va `method` ni Toast bilan tasdiqlash.
          const status = res?.status ?? res?.data?.status ?? 'paid';
          const method = res?.method ?? res?.data?.method ?? data.method;
          const methodLabel = method === 'fakt' ? 'Fakt' : 'Naqt';
          const statusLabel = status === 'paid' ? "to'langan" : status;
          showSuccess(`${data.period} davri uchun ${methodLabel} usulida ${statusLabel}`);
          onOpenChange(false);
        },
        onError: (err) =>
          handleMutationError(err, {
            setError,
            fields: ['period', 'method'],
            conflictField: 'period',
            statusMessages: {
              409: "Bu davr uchun to'lov allaqachon belgilangan",
            },
          }),
      }
    );
  }

  const validatePeriod = (v) => validators.period(v) || true;
  const isFakt = client?.payment_type === 'fakt';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[520px] p-0">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          {/* Header */}
          <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
            <div className="space-y-1">
              <h2 className="text-xl font-semibold text-[var(--text-primary)]">
                Oylik to'lovni belgilash
              </h2>
              <p className="text-sm text-[var(--text-secondary)]">
                {client?.name ? <span className="font-medium text-[var(--text-primary)]">{client.name}</span> : null}
                {client?.name ? ' uchun' : ''} davr va usulni tanlang
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
            {/* R10.5 ogohlantirish: fakt mijozlari */}
            {isFakt && (
              <div className="flex items-start gap-3 rounded-btn border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning-text">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                  Bu mijozning to'lov turi <span className="font-semibold">Fakt</span>. Faktura orqali to'lov
                  avtomatik hisoblanadi. Qo'lda belgilash kamdan-kam holatlarda zarur bo'ladi.
                </p>
              </div>
            )}

            {/* Period */}
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
              disabled={mutation.isPending}
              className="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-60"
            >
              {mutation.isPending ? 'Saqlanmoqda…' : 'Belgilash'}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
