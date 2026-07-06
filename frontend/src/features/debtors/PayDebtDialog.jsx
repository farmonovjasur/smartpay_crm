import { useEffect, useMemo, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, CreditCard, Banknote, AlertCircle, CheckCircle2, ArrowRight } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess, showInfo } from '@/lib/toast';
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

const MIN_AMOUNT = 1000;

/**
 * Qarzni to'liq yoki qisman to'lash dialogi.
 *
 * Yangilangan logika:
 * - Summa kiritish maydoni mavjud (default = qarz qoldig'i)
 * - Minimal summa: 1 000 so'm
 * - Qolgan qarz real-time ko'rsatiladi
 * - Ortiqcha summa mijoz balansiga tushadi
 *
 * @param {{
 *   open: boolean,
 *   onOpenChange: (v: boolean) => void,
 *   debt: { id: string|number, client_name: string, amount: string, paid_amount: string, remaining_amount: string, payment_type_snapshot: string },
 * }} props
 */
export function PayDebtDialog({ open, onOpenChange, debt }) {
  const mutation = usePayDebt(debt?.id);

  // Default method — qarz `payment_type_snapshot` ga qarab.
  const defaultMethod = debt?.payment_type_snapshot === 'naqt' ? 'naqt' : 'fakt';

  const remainingAmount = useMemo(() => {
    if (!debt) return '0';
    return debt.remaining_amount ?? debt.amount ?? '0';
  }, [debt]);

  const {
    handleSubmit,
    reset,
    control,
    watch,
    formState: { isSubmitting, errors },
  } = useForm({
    defaultValues: {
      method: defaultMethod,
      amount: '',
    },
  });

  useEffect(() => {
    if (open) {
      reset({
        method: defaultMethod,
        amount: remainingAmount,
      });
    }
  }, [open, defaultMethod, remainingAmount, reset]);

  const watchedAmount = watch('amount');
  const enteredAmount = parseFloat(watchedAmount) || 0;
  const remaining = parseFloat(remainingAmount) || 0;

  // Hisoblashlar
  const diff = remaining - enteredAmount;
  const isPartial = enteredAmount > 0 && enteredAmount < remaining;
  const isExact = enteredAmount > 0 && Math.abs(diff) < 0.01;
  const isOverpayment = enteredAmount > remaining;
  const overpayment = isOverpayment ? (enteredAmount - remaining).toFixed(2) : '0.00';
  const remainingAfter = isPartial ? diff.toFixed(2) : '0.00';

  function onSubmit(data) {
    mutation.mutate(
      { method: data.method, amount: String(data.amount) },
      {
        onSuccess: (res) => {
          const d = res?.data;
          if (d?.fully_paid) {
            if (parseFloat(d.overpayment) > 0) {
              showSuccess(
                `Qarz to'liq to'landi! ${formatMoney(d.overpayment)} so'm balansga tushdi.`
              );
            } else {
              showSuccess(`Qarz to'liq to'landi (${data.method === 'fakt' ? 'Fakt' : 'Naqt'})`);
            }
          } else {
            showInfo(
              `Qisman to'lov qabul qilindi. Qolgan qarz: ${formatMoney(d?.remaining_amount)} so'm`
            );
          }
          onOpenChange(false);
        },
        onError: (err) => {
          const status = err?.response?.status;
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
      <DialogContent className="max-w-[520px] p-0">
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
                — to'lov summasi va usulini kiriting
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
          <div className="space-y-6 p-6 max-h-[75vh] overflow-y-auto">
            {/* Qarz summasi (read-only) */}
            <div className="rounded-card border border-[var(--border)] bg-bg-light p-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
                    Umumiy qarz
                  </p>
                  <p className="mt-1 text-lg font-bold text-[var(--text-primary)]">
                    {formatMoney(debt?.amount)}{' '}
                    <span className="text-sm font-medium text-[var(--text-secondary)]">so'm</span>
                  </p>
                </div>
                {parseFloat(debt?.paid_amount) > 0 && (
                  <div className="text-right">
                    <p className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
                      To'langan
                    </p>
                    <p className="mt-1 text-lg font-bold text-success">
                      {formatMoney(debt?.paid_amount)}{' '}
                      <span className="text-sm font-medium">so'm</span>
                    </p>
                  </div>
                )}
              </div>
              {parseFloat(debt?.paid_amount) > 0 && (
                <div className="mt-3 flex items-center gap-2 rounded-md bg-warning-bg px-3 py-2">
                  <AlertCircle className="h-4 w-4 text-warning-text shrink-0" />
                  <span className="text-xs font-medium text-warning-text">
                    Qolgan qarz: {formatMoney(remainingAmount)} so'm
                  </span>
                </div>
              )}
            </div>

            {/* Summa kiritish maydoni */}
            <div className="space-y-2">
              <label className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
                To'lov summasi <span className="text-danger">*</span>
              </label>
              <Controller
                control={control}
                name="amount"
                rules={{
                  required: "Summa kiritish shart",
                  validate: (v) => {
                    const n = parseFloat(v);
                    if (isNaN(n) || n <= 0) return "Summani to'g'ri kiriting";
                    if (n < MIN_AMOUNT) return `Minimal summa ${MIN_AMOUNT.toLocaleString()} so'm`;
                    return true;
                  },
                }}
                render={({ field }) => (
                  <div className="relative">
                    <input
                      {...field}
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="Summani kiriting"
                      className={cn(
                        'h-12 w-full rounded-btn border bg-[var(--card-bg)] px-4 text-base font-semibold outline-none transition-colors placeholder:text-[var(--text-secondary)] focus:ring-2',
                        errors.amount
                          ? 'border-danger focus:ring-danger/30'
                          : 'border-[var(--border)] focus:ring-primary'
                      )}
                    />
                    <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm font-medium text-[var(--text-secondary)]">
                      so'm
                    </span>
                  </div>
                )}
              />
              {errors.amount && (
                <p className="text-xs text-danger">{errors.amount.message}</p>
              )}

              {/* Quick fill buttons */}
              <div className="flex flex-wrap gap-2 mt-2">
                <QuickFillBtn
                  label={`To'liq: ${formatMoney(remainingAmount)}`}
                  onClick={() => reset((prev) => ({ ...prev, amount: remainingAmount }))}
                  active={isExact}
                />
                {debt?.monthly_amount && parseFloat(debt.monthly_amount) < parseFloat(remainingAmount) && (
                  <QuickFillBtn
                    label={`1 oy: ${formatMoney(debt.monthly_amount)}`}
                    onClick={() => reset((prev) => ({ ...prev, amount: debt.monthly_amount }))}
                    active={Math.abs(enteredAmount - parseFloat(debt.monthly_amount)) < 0.01}
                  />
                )}
              </div>
            </div>

            {/* To'lov natijasi — real-time preview */}
            {enteredAmount >= MIN_AMOUNT && (
              <div
                className={cn(
                  'rounded-card border p-4 space-y-2 transition-colors',
                  isExact
                    ? 'border-success/40 bg-success-bg'
                    : isOverpayment
                      ? 'border-info/40 bg-info-bg'
                      : 'border-warning/40 bg-warning-bg'
                )}
              >
                {isExact && (
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="h-4 w-4 text-success-text" />
                    <span className="text-sm font-medium text-success-text">
                      Qarz to'liq yopiladi
                    </span>
                  </div>
                )}
                {isPartial && (
                  <>
                    <div className="flex items-center gap-2">
                      <ArrowRight className="h-4 w-4 text-warning-text" />
                      <span className="text-sm font-medium text-warning-text">
                        Qisman to'lov
                      </span>
                    </div>
                    <p className="text-xs text-warning-text">
                      To'lovdan keyin qolgan qarz:{' '}
                      <span className="font-bold">{formatMoney(remainingAfter)} so'm</span>
                    </p>
                  </>
                )}
                {isOverpayment && (
                  <>
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="h-4 w-4 text-info-text" />
                      <span className="text-sm font-medium text-info-text">
                        Qarz to'liq yopiladi + ortiqcha balansga tushadi
                      </span>
                    </div>
                    <p className="text-xs text-info-text">
                      Balansga tushadigan summa:{' '}
                      <span className="font-bold">{formatMoney(overpayment)} so'm</span>
                    </p>
                  </>
                )}
              </div>
            )}

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
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
          <div className="flex justify-end gap-3 border-t border-[var(--border)] p-6 bg-bg-light/50">
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
              {submitting ? "To'lanmoqda…" : isPartial ? "Qisman to'lash" : "To'lashni tasdiqlash"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function QuickFillBtn({ label, onClick, active }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
        active
          ? 'bg-primary text-white shadow-sm'
          : 'bg-[var(--card-bg)] text-[var(--text-secondary)] hover:bg-bg-light border border-[var(--border)]'
      )}
    >
      {label}
    </button>
  );
}
