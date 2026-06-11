import { useEffect, useState } from 'react';
import { Copy, Check, X, KeyRound, AlertTriangle } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { mapErrorToMessage } from '@/lib/errors';
import { showError, showSuccess } from '@/lib/toast';
import { useResetPassword } from './hooks';
import { cn } from '@/lib/utils';

/**
 * Foydalanuvchi parolini qayta tiklash dialogi.
 * R18.5: Yangi parol javobdan olinadi va admin uchun BIR MARTA ko'rsatiladi.
 *
 * @param {{
 *   open: boolean,
 *   onOpenChange: (v: boolean) => void,
 *   user: { id: number|string, name: string, email: string } | null,
 * }} props
 */
export function ResetPasswordDialog({ open, onOpenChange, user }) {
  const reset = useResetPassword();
  const [newPassword, setNewPassword] = useState(null);
  const [copied, setCopied] = useState(false);

  // Dialog yopilganda parol clear qilinadi.
  useEffect(() => {
    if (!open) {
      setNewPassword(null);
      setCopied(false);
      reset.reset();
    }
  }, [open, reset]);

  function handleConfirm() {
    if (!user) return;
    reset.mutate(user.id, {
      onSuccess: (res) => {
        const pwd =
          res?.password ?? res?.new_password ?? res?.newPassword ?? res?.data?.password ?? '';
        if (!pwd) {
          showError('Parol javobdan olib bo\'lmadi');
          return;
        }
        setNewPassword(pwd);
      },
      onError: (err) => {
        showError(
          mapErrorToMessage({
            status: err?.response?.status ?? null,
            body: err?.response?.data,
            isNetwork: !err?.response,
          })
        );
      },
    });
  }

  async function handleCopy() {
    if (!newPassword) return;
    try {
      await navigator.clipboard.writeText(newPassword);
      setCopied(true);
      showSuccess('Parol nusxalandi');
      setTimeout(() => setCopied(false), 2000);
    } catch {
      showError('Nusxalashda xatolik');
    }
  }

  const showResult = newPassword !== null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[480px] p-0">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-[var(--border)] p-6">
          <div className="flex items-start gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-warning-bg">
              <KeyRound className="h-5 w-5 text-warning-text" />
            </span>
            <div className="space-y-1">
              <h2 className="text-xl font-semibold text-[var(--text-primary)]">
                Parolni qayta tiklash
              </h2>
              <p className="text-sm text-[var(--text-secondary)]">
                {user?.name && (
                  <>
                    <span className="font-medium text-[var(--text-primary)]">{user.name}</span>
                    {' '}({user.email}) uchun yangi parol yaratiladi
                  </>
                )}
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
        <div className="space-y-4 p-6">
          {!showResult ? (
            <div className="flex items-start gap-3 rounded-btn border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning-text">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
              <p>
                Yangi parol generatsiya qilinadi va eski parol bekor qilinadi. Yangi parol bu
                dialogda <span className="font-semibold">faqat bir marta</span> ko'rsatiladi.
              </p>
            </div>
          ) : (
            <>
              <div className="flex items-start gap-3 rounded-btn border border-success/30 bg-success-bg px-4 py-3 text-sm text-success-text">
                <Check className="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                  Yangi parol yaratildi. Foydalanuvchiga uzating va dialogni yoping —
                  <span className="font-semibold"> bu parol qayta ko'rsatilmaydi</span>.
                </p>
              </div>

              <div className="space-y-2">
                <label className="text-xs uppercase tracking-wide text-[var(--text-secondary)]">
                  Yangi parol
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    readOnly
                    value={newPassword}
                    onFocus={(e) => e.target.select()}
                    className="h-11 flex-1 rounded-btn border border-[var(--border)] bg-bg-light px-4 font-mono text-sm text-[var(--text-primary)]"
                  />
                  <button
                    type="button"
                    onClick={handleCopy}
                    className={cn(
                      'flex h-11 w-11 items-center justify-center rounded-btn border transition-colors',
                      copied
                        ? 'border-success bg-success-bg text-success-text'
                        : 'border-[var(--border)] bg-[var(--card-bg)] text-[var(--text-secondary)] hover:bg-bg-light'
                    )}
                    aria-label="Nusxa olish"
                  >
                    {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                  </button>
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-3 border-t border-[var(--border)] p-6">
          {!showResult ? (
            <>
              <button
                type="button"
                onClick={() => onOpenChange(false)}
                className="rounded-btn border border-[var(--border)] px-5 py-2.5 text-sm font-medium text-[var(--text-secondary)] hover:bg-bg-light"
              >
                Bekor qilish
              </button>
              <button
                type="button"
                onClick={handleConfirm}
                disabled={reset.isPending}
                className="rounded-btn bg-warning px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:opacity-60"
              >
                {reset.isPending ? 'Yaratilmoqda…' : "Parolni qayta tiklash"}
              </button>
            </>
          ) : (
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="rounded-btn bg-primary px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
            >
              Yopish
            </button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
