import { useEffect, forwardRef } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { X, CreditCard, Banknote, ClipboardList } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { validators } from '@/lib/validation';
import { handleMutationError } from '@/lib/mutationErrors';
import { useCreateClient, useUpdateClient } from './hooks';
import { showSuccess } from '@/lib/toast';
import { cn } from '@/lib/utils';

const FIELDS = ['inn', 'name', 'phone', 'phone2', 'service_date', 'payment_type', 'product_count', 'notes', 'status', 'last_paid_period'];

const PAYMENT_OPTIONS = [
  { value: 'fakt', icon: CreditCard, label: 'Fakt', desc: 'Online', iconBg: 'bg-primary-bg', iconColor: 'text-primary' },
  { value: 'naqt', icon: Banknote, label: 'Naqt', desc: 'Naqd', iconBg: 'bg-teal-bg', iconColor: 'text-teal' },
  { value: 'qarz', icon: ClipboardList, label: 'Qarz', desc: 'Kreditga', iconBg: 'bg-warning-bg', iconColor: 'text-warning-text' },
];

/**
 * Backend `last_paid_period` ni YYYY-MM formatida saqlaydi, lekin foydalanuvchi
 * to'liq sanani DD.MM.YYYY tartibda kiritadi. Form ichida HTML5 date input
 * (YYYY-MM-DD qiymat) bilan ishlaymiz va backendga yuborishdan oldin "YYYY-MM"
 * ga qisqartiramiz.
 */
function periodToFullDate(period) {
  if (!period || typeof period !== 'string') return '';
  // "2026-04" -> "2026-04-01"
  return /^\d{4}-(0[1-9]|1[0-2])$/.test(period) ? `${period}-01` : '';
}

function fullDateToPeriod(fullDate) {
  if (!fullDate || typeof fullDate !== 'string') return '';
  // "2026-04-15" -> "2026-04"
  const m = fullDate.match(/^(\d{4})-(\d{2})/);
  return m ? `${m[1]}-${m[2]}` : '';
}

function validateLastPaidDate(v) {
  if (!v) return "Oxirgi to'langan sana majburiy";
  const period = fullDateToPeriod(v);
  if (!period) return 'Sana noto\'g\'ri';
  // service_date kelajakda emasligi tekshirilgan, bu yerda esa periodning o'zi joriy oydan keyin emasligi
  const today = new Date();
  const currentPeriod = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
  if (period > currentPeriod) return "Oxirgi to'langan sana joriy oydan keyin bo'lishi mumkin emas";
  return true;
}

/**
 * @param {{ open: boolean, onOpenChange: (v:boolean)=>void, client?: object }} props
 */
export function ClientForm({ open, onOpenChange, client }) {
  const isEdit = !!client;
  const { register, handleSubmit, setError, reset, control, formState: { errors } } = useForm({
    defaultValues: { payment_type: 'fakt', status: 'faol', product_count: 1, last_paid_period: '' },
  });

  useEffect(() => {
    if (open) {
      if (client) {
        // Backenddan kelgan "YYYY-MM" ni date input uchun "YYYY-MM-01" ga aylantiramiz
        reset({
          ...client,
          notes: client.notes ?? '',
          phone2: client.phone2 ?? '',
          last_paid_period: periodToFullDate(client.last_paid_period),
        });
      } else {
        reset({ payment_type: 'fakt', status: 'faol', product_count: 1, last_paid_period: '', phone2: '' });
      }
    }
  }, [open, client, reset]);

  const create = useCreateClient();
  const update = useUpdateClient();
  const mutation = isEdit ? update : create;

  function onSubmit(data) {
    const payload = {
      ...data,
      last_paid_period: fullDateToPeriod(data.last_paid_period),
    };
    if (isEdit) payload.id = client.id;

    mutation.mutate(payload, {
      onSuccess: () => {
        showSuccess(isEdit ? 'Mijoz yangilandi' : 'Mijoz yaratildi');
        onOpenChange(false);
      },
      onError: (err) =>
        handleMutationError(err, {
          setError,
          fields: FIELDS,
          conflictField: 'inn',
          statusMessages: {
            409: 'Bu INN bilan mijoz allaqachon mavjud',
          },
        }),
    });
  }

  const validate = (key) => (v) => validators[key](v) || true;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[520px] p-0">
        <form onSubmit={handleSubmit(onSubmit)}>
          {/* Header */}
          <div className="flex items-center justify-between border-b border-[var(--border)] px-5 py-3">
            <h2 className="text-base font-semibold text-[var(--text-primary)]">
              {isEdit ? 'Mijozni tahrirlash' : "Yangi mijoz qo'shish"}
            </h2>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="flex h-7 w-7 items-center justify-center rounded-md text-[var(--text-secondary)] hover:bg-bg-light"
              aria-label="Yopish"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Body — kompakt */}
          <div className="space-y-3 px-5 py-4">
            <Field label="Mijoz nomi" required error={errors.name?.message}>
              <TextInput placeholder="Kompaniya yoki F.I.O" {...register('name', { validate: validate('name') })} />
            </Field>

            <div className="grid grid-cols-2 gap-3">
              <Field label="INN" required error={errors.inn?.message}>
                <TextInput placeholder="123456789" disabled={isEdit} {...register('inn', { validate: validate('inn') })} />
              </Field>
              <Field label="Telefon" required error={errors.phone?.message}>
                <TextInput placeholder="+998XXXXXXXXX" {...register('phone', { validate: validate('phone') })} />
              </Field>
            </div>

            <Field label="Qo'shimcha telefon" error={errors.phone2?.message}>
              <TextInput placeholder="+998XXXXXXXXX (ixtiyoriy)" {...register('phone2')} />
            </Field>

            <div className="grid grid-cols-2 gap-3">
              <Field label="Xizmatga ulangan sana" required error={errors.service_date?.message}>
                <TextInput type="date" {...register('service_date', { validate: validate('service_date') })} />
              </Field>
              <Field label="Mahsulot soni" required error={errors.product_count?.message}>
                <TextInput type="number" min={1} {...register('product_count', { valueAsNumber: true, validate: validate('product_count') })} />
              </Field>
            </div>

            <Field
              label="Oxirgi to'langan sana"
              required
              error={errors.last_paid_period?.message}
              hint="Mijozning oxirgi to'lov qilgan oyini tanlang. Yangi mijoz bo'lsa — joriy oyni"
            >
              <TextInput
                type="date"
                {...register('last_paid_period', { validate: validateLastPaidDate })}
              />
            </Field>

            {/* Payment type — kompakt gorizontal kartochkalar */}
            <div className="space-y-1.5">
              <label className="text-sm font-medium text-[var(--text-primary)]">
                To'lov turi <span className="text-danger">*</span>
              </label>
              <Controller
                control={control}
                name="payment_type"
                render={({ field }) => (
                  <div className="grid grid-cols-3 gap-2">
                    {PAYMENT_OPTIONS.map((opt) => {
                      const selected = field.value === opt.value;
                      return (
                        <button
                          type="button"
                          key={opt.value}
                          onClick={() => field.onChange(opt.value)}
                          className={cn(
                            'flex items-center gap-2 rounded-btn border px-3 py-2 text-left transition-colors',
                            selected
                              ? 'border-2 border-primary bg-primary-bg'
                              : 'border border-[var(--border)] bg-[var(--card-bg)] hover:border-[var(--border-dark)]'
                          )}
                        >
                          <span className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-md', opt.iconBg)}>
                            <opt.icon className={cn('h-3.5 w-3.5', opt.iconColor)} />
                          </span>
                          <span className="flex flex-col leading-tight">
                            <span className={cn('text-xs font-semibold', selected ? 'text-primary' : 'text-[var(--text-primary)]')}>
                              {opt.label}
                            </span>
                            <span className="text-[10px] text-[var(--text-secondary)]">{opt.desc}</span>
                          </span>
                        </button>
                      );
                    })}
                  </div>
                )}
              />
            </div>

            {isEdit && (
              <Field label="Holat">
                <select
                  {...register('status')}
                  className="h-9 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="faol">Faol</option>
                  <option value="nofaol">Nofaol</option>
                </select>
              </Field>
            )}

            {errors.root && <p className="text-xs text-danger">{errors.root.message}</p>}
          </div>

          {/* Footer */}
          <div className="flex justify-end gap-2 border-t border-[var(--border)] px-5 py-3">
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="rounded-btn border border-[var(--border)] px-4 py-2 text-sm font-medium text-[var(--text-secondary)] hover:bg-bg-light"
            >
              Bekor qilish
            </button>
            <button
              type="submit"
              disabled={mutation.isPending}
              className="rounded-btn bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover disabled:opacity-60"
            >
              {mutation.isPending ? 'Saqlanmoqda...' : isEdit ? 'Saqlash' : "Qo'shish"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, required, error, hint, children }) {
  return (
    <div className="space-y-1">
      <label className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
        {label} {required && <span className="text-danger">*</span>}
      </label>
      {children}
      {hint && !error && <p className="text-[11px] leading-tight text-[var(--text-secondary)]">{hint}</p>}
      {error && <p className="text-[11px] text-danger">{error}</p>}
    </div>
  );
}

const TextInput = forwardRef(function TextInput({ className, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={cn(
        'h-9 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm outline-none placeholder:text-[#94A3B8] focus:ring-2 focus:ring-primary disabled:bg-bg-light disabled:opacity-70',
        className
      )}
      {...props}
    />
  );
});
