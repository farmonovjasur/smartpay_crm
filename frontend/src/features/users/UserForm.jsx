import { useEffect, forwardRef } from 'react';
import { useForm } from 'react-hook-form';
import { X, Crown, User as UserIcon } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { validators } from '@/lib/validation';
import { handleMutationError } from '@/lib/mutationErrors';
import { showSuccess } from '@/lib/toast';
import { useCreateUser, useUpdateUser } from './hooks';
import { cn } from '@/lib/utils';

const FIELDS = ['name', 'email', 'password', 'role', 'is_active'];

/**
 * Foydalanuvchi qo'shish/tahrirlash formasi.
 * R18.2 — POST `name`, `email`, `password`, `role`
 * R18.4 — PUT `name`, `email`, `role`, `is_active`
 *
 * @param {{
 *   open: boolean,
 *   onOpenChange: (v: boolean) => void,
 *   user?: { id: number|string, name: string, email: string, role: string, is_active: boolean } | null,
 * }} props
 */
export function UserForm({ open, onOpenChange, user }) {
  const isEdit = !!user;
  const create = useCreateUser();
  const update = useUpdateUser();
  const mutation = isEdit ? update : create;

  const {
    register,
    handleSubmit,
    setError,
    reset,
    watch,
    setValue,
    formState: { errors },
  } = useForm({
    defaultValues: {
      name: '',
      email: '',
      password: '',
      role: 'user',
      is_active: true,
    },
  });

  const role = watch('role');
  const isActive = watch('is_active');

  useEffect(() => {
    if (!open) return;
    if (user) {
      reset({
        name: user.name || '',
        email: user.email || '',
        password: '',
        role: user.role || 'user',
        is_active: user.is_active ?? true,
      });
    } else {
      reset({ name: '', email: '', password: '', role: 'user', is_active: true });
    }
  }, [open, user, reset]);

  function onSubmit(data) {
    // Edit: password yuborilmaydi (alohida reset-password orqali tiklanadi).
    const payload = isEdit
      ? { id: user.id, name: data.name, email: data.email, role: data.role, is_active: data.is_active }
      : { name: data.name, email: data.email, password: data.password, role: data.role };

    mutation.mutate(payload, {
      onSuccess: () => {
        showSuccess(isEdit ? 'Foydalanuvchi yangilandi' : 'Foydalanuvchi yaratildi');
        onOpenChange(false);
      },
      onError: (err) =>
        handleMutationError(err, {
          setError,
          fields: FIELDS,
          conflictField: 'email',
          statusMessages: {
            409: "Bu email allaqachon ro'yxatdan o'tgan",
          },
        }),
    });
  }

  // R18.7: email RFC, password majburiy yaratishda.
  const validateEmail = (v) => validators.email(v) || true;
  const validateName = (v) => validators.name(v) || true;
  const validatePassword = (v) => {
    if (isEdit) return true; // edit'da password yuborilmaydi
    if (!v) return 'Parol majburiy';
    if (v.length < 8) return "Parol kamida 8 belgidan iborat bo'lishi kerak";
    return true;
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[520px] p-0">
        <form onSubmit={handleSubmit(onSubmit)} noValidate>
          {/* Header */}
          <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
            <div className="space-y-1">
              <h2 className="text-xl font-semibold text-[var(--text-primary)]">
                {isEdit ? 'Foydalanuvchini tahrirlash' : 'Yangi foydalanuvchi'}
              </h2>
              <p className="text-sm text-[var(--text-secondary)]">
                Barcha majburiy maydonlarni to'ldiring
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
            <Field label="Ism" required error={errors.name?.message}>
              <Input
                placeholder="F.I.Sh"
                {...register('name', { validate: validateName })}
              />
            </Field>

            <Field label="Email" required error={errors.email?.message}>
              <Input
                type="email"
                autoComplete="email"
                placeholder="user@smartpay.uz"
                disabled={isEdit ? false : false}
                {...register('email', { validate: validateEmail })}
              />
            </Field>

            {!isEdit && (
              <Field
                label="Parol"
                required
                error={errors.password?.message}
                hint="Kamida 8 belgi"
              >
                <Input
                  type="password"
                  autoComplete="new-password"
                  placeholder="••••••••"
                  {...register('password', { validate: validatePassword })}
                />
              </Field>
            )}

            {/* Role radio cards */}
            <div className="space-y-2">
              <label className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
                Rol <span className="text-danger">*</span>
              </label>
              <div className="grid grid-cols-2 gap-3">
                <RoleCard
                  selected={role === 'user'}
                  onClick={() => setValue('role', 'user', { shouldDirty: true })}
                  icon={UserIcon}
                  label="Foydalanuvchi"
                  desc="Standart kirish (R/W)"
                  iconBg="bg-info-bg"
                  iconColor="text-info"
                />
                <RoleCard
                  selected={role === 'admin'}
                  onClick={() => setValue('role', 'admin', { shouldDirty: true })}
                  icon={Crown}
                  label="Admin"
                  desc="Foydalanuvchilar va audit ham"
                  iconBg="bg-purple-bg"
                  iconColor="text-purple-text"
                />
              </div>
            </div>

            {/* is_active toggle (faqat edit) */}
            {isEdit && (
              <div className="flex items-center justify-between rounded-card border border-[var(--border)] bg-bg-light p-4">
                <div>
                  <p className="text-sm font-medium text-[var(--text-primary)]">Holat</p>
                  <p className="text-xs text-[var(--text-secondary)]">
                    Nofaol foydalanuvchi tizimga kira olmaydi
                  </p>
                </div>
                <label className="inline-flex cursor-pointer items-center gap-2">
                  <input
                    type="checkbox"
                    checked={!!isActive}
                    onChange={(e) => setValue('is_active', e.target.checked, { shouldDirty: true })}
                    className="h-5 w-5 rounded border-[var(--border)] text-primary focus:ring-primary"
                  />
                  <span className="text-sm font-semibold text-[var(--text-primary)]">
                    {isActive ? 'Faol' : 'Nofaol'}
                  </span>
                </label>
              </div>
            )}
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
              {mutation.isPending ? 'Saqlanmoqda…' : isEdit ? 'Saqlash' : "Foydalanuvchi qo'shish"}
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function Field({ label, required, error, hint, children }) {
  return (
    <div className="space-y-2">
      <label className="flex items-center gap-1 text-sm font-medium text-[var(--text-primary)]">
        {label} {required && <span className="text-danger">*</span>}
      </label>
      {children}
      {hint && !error && <p className="text-xs text-[var(--text-secondary)]">{hint}</p>}
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
}

const Input = forwardRef(function Input({ className, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={cn(
        'h-11 w-full rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-4 text-sm outline-none placeholder:text-[#94A3B8] focus:ring-2 focus:ring-primary disabled:bg-bg-light disabled:opacity-70',
        className
      )}
      {...props}
    />
  );
});

function RoleCard({ selected, onClick, icon: Icon, label, desc, iconBg, iconColor }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={cn(
        'flex flex-col items-start gap-2 rounded-btn p-4 text-left transition-colors',
        selected
          ? 'border-2 border-primary bg-primary-bg'
          : 'border border-[var(--border)] bg-[var(--card-bg)] hover:border-[var(--border-dark)]'
      )}
    >
      <span className={cn('flex h-8 w-8 items-center justify-center rounded-md', iconBg)}>
        <Icon className={cn('h-4 w-4', iconColor)} />
      </span>
      <span
        className={cn(
          'text-sm font-semibold',
          selected ? 'text-primary' : 'text-[var(--text-primary)]'
        )}
      >
        {label}
      </span>
      <span
        className={cn(
          'text-[11px] leading-tight',
          selected ? 'text-primary' : 'text-[var(--text-secondary)]'
        )}
      >
        {desc}
      </span>
    </button>
  );
}
