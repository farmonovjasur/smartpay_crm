import { useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, CreditCard, Banknote, AlertTriangle, Calculator } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { usePrepay } from './hooks';
import { cn } from '@/lib/utils';
import { formatMoney } from '@/lib/money';

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

export function PrepayDialog({ open, onOpenChange, client }) {
  const mutation = usePrepay(client?.id);
  const defaultMethod = client?.payment_type === 'fakt' ? 'fakt' : 'naqt';
  
  const [selectedMonths, setSelectedMonths] = useState(null);

  const {
    register,
    handleSubmit,
    reset,
    setError,
    control,
    watch,
    setValue,
    formState: { errors },
  } = useForm({
    defaultValues: { amount: '', method: defaultMethod, notes: '' },
  });

  const amountValue = watch('amount');

  useEffect(() => {
    if (open) {
      reset({ amount: '', method: defaultMethod, notes: '' });
      setSelectedMonths(null);
    }
  }, [open, defaultMethod, reset]);

  // Calculate monthly amount for quick buttons
  // Assuming unit_price is 100,000 for calculation if we don't have it explicitly, 
  // but client.monthly_amount has it already calculated if it's available.
  const monthlyAmount = client?.monthly_amount ? Number(client.monthly_amount) : 0;
  
  const estimatedMonths = monthlyAmount > 0 && amountValue && !isNaN(Number(amountValue))
    ? Math.floor(Number(amountValue) / monthlyAmount)
    : 0;

  function handleQuickSelect(months) {
    if (monthlyAmount > 0) {
      const total = monthlyAmount * months;
      setValue('amount', String(total), { shouldValidate: true });
      setSelectedMonths(months);
    }
  }

  function onSubmit(data) {
    mutation.mutate(
      { amount: data.amount, method: data.method, notes: data.notes },
      {
        onSuccess: (res) => {
          showSuccess(res?.message || "Oldindan to'lov muvaffaqiyatli saqlandi");
          onOpenChange(false);
        },
        onError: (err) =>
          handleMutationError(err, {
            setError,
            fields: ['amount', 'method', 'notes'],
          }),
      }
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[520px] p-0">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          {/* Header */}
          <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
            <div className="space-y-1">
              <h2 className="text-xl font-semibold text-[var(--text-primary)]">
                Oldindan to'lov
              </h2>
              <p className="text-sm text-[var(--text-secondary)]">
                {client?.name ? <span className="font-medium text-[var(--text-primary)]">{client.name}</span> : null}
                {client?.name ? ' balansini to\'ldirish' : ''}
              </p>
            </div>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="flex h-8 w-8 items-center justify-center rounded-md bg-bg-light text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]"
            >
              <X className="h-[18px] w-[18px]" />
            </button>
          </div>

          {/* Body */}
          <div className="space-y-5 p-6">
            {/* Amount */}
            <div className="space-y-3">
              <label htmlFor="amount" className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
                Summa <span className="text-danger">*</span>
              </label>
              
              {monthlyAmount > 0 && (
                <div className="flex flex-wrap gap-2 pb-2">
                  {[1, 3, 6, 12].map((m) => (
                    <button
                      key={m}
                      type="button"
                      onClick={() => handleQuickSelect(m)}
                      className={cn(
                        "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
                        selectedMonths === m 
                          ? "border-primary bg-primary text-white" 
                          : "border-[var(--border)] bg-[var(--card-bg)] text-[var(--text-secondary)] hover:border-primary hover:text-primary"
                      )}
                    >
                      {m} oylik
                    </button>
                  ))}
                </div>
              )}

              <div className="relative">
                <input
                  id="amount"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Masalan: 600000"
                  aria-invalid={!!errors.amount}
                  {...register('amount', { 
                    required: "Summa kiritilishi shart",
                    min: { value: 1, message: "Summa noldan katta bo'lishi kerak" },
                    onChange: () => setSelectedMonths(null)
                  })}
                  className={cn(
                    'h-11 w-full rounded-btn border bg-[var(--card-bg)] px-4 pr-12 text-sm outline-none focus:ring-2 focus:ring-primary',
                    errors.amount ? 'border-danger' : 'border-[var(--border)]'
                  )}
                />
                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-[var(--text-secondary)]">
                  UZS
                </span>
              </div>
              
              <div className="flex items-start justify-between">
                {errors.amount ? (
                  <p className="text-xs text-danger">{errors.amount.message}</p>
                ) : (
                  <p className="text-xs text-[var(--text-secondary)]">To'lanayotgan pul miqdori</p>
                )}
                
                {estimatedMonths > 0 && (
                  <span className="inline-flex items-center gap-1 text-xs font-medium text-success-text">
                    <Calculator className="h-3 w-3" />
                    ~{estimatedMonths} oyga yetadi
                  </span>
                )}
              </div>
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

            {/* Notes */}
            <div className="space-y-2">
              <label htmlFor="notes" className="text-sm font-medium text-[var(--text-primary)]">
                Izoh (ixtiyoriy)
              </label>
              <textarea
                id="notes"
                rows={2}
                {...register('notes')}
                className="w-full resize-none rounded-btn border border-[var(--border)] bg-[var(--card-bg)] p-3 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                placeholder="Qo'shimcha ma'lumotlar..."
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
              {mutation.isPending ? 'Saqlanmoqda…' : "Oldindan to'lov qilish"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
