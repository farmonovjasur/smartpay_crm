import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from '@tanstack/react-router';
import { useSelector } from 'react-redux';
import { Mail, Lock, Eye, EyeOff, ArrowRight, Moon, Sun } from 'lucide-react';
import { useLogin } from './hooks';
import { parseRetryAfter } from '@/lib/retryAfter';
import { useTheme } from '@/hooks/useTheme';
import { useT } from '@/lib/i18n';
import { LanguageSwitcher } from '@/components/common';
import { cn } from '@/lib/utils';

export default function LoginPage() {
  const t = useT();
  const { register, handleSubmit, formState: { errors } } = useForm();
  const login = useLogin();
  const navigate = useNavigate();
  const redirectTo = useSelector((s) => s.ui.redirectTo);
  const [serverError, setServerError] = useState(null);
  const [countdown, setCountdown] = useState(0);
  const [showPassword, setShowPassword] = useState(false);
  const { isDark, toggle: toggleDarkMode } = useTheme();

  async function onSubmit(data) {
    setServerError(null);
    login.mutate(data, {
      onSuccess: () => navigate({ to: redirectTo || '/dashboard' }),
      onError: (err) => {
        const status = err.response?.status;
        if (status === 429) {
          const secs = parseRetryAfter(err.response?.headers?.['retry-after']);
          setCountdown(secs);
          setServerError(t('auth.rateLimited', { seconds: secs }));
          const timer = setInterval(() => {
            setCountdown((c) => {
              if (c <= 1) { clearInterval(timer); return 0; }
              return c - 1;
            });
          }, 1000);
        } else if (status === 401) {
          setServerError(t('auth.invalidCredentials'));
        } else {
          setServerError(err.normalized?.message || t('errors.unknown'));
        }
      },
    });
  }

  const isDisabled = login.isPending || countdown > 0;

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-[var(--bg-light)] px-4">
      {/* Top controls */}
      <div className="absolute right-8 top-8 flex items-center gap-2.5">
        <LanguageSwitcher />
        <button type="button" onClick={toggleDarkMode} className="flex h-9 w-9 items-center justify-center rounded-full border border-[var(--border)] bg-bg-light text-[var(--text-secondary)] transition-colors hover:text-[var(--text-primary)]" aria-label={isDark ? t('layout.lightMode') : t('layout.darkMode')}>
          {isDark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
        </button>
      </div>

      <div className="w-full max-w-[400px]">
        {/* Head */}
        <div className="space-y-2">
          <h1 className="text-3xl font-bold text-[var(--text-primary)]">{t('auth.loginTitle')}</h1>
          <p className="text-sm text-[var(--text-secondary)]">{t('auth.loginSubtitle')}</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="mt-7 space-y-6">
          <div className="space-y-[18px]">
            {/* Email */}
            <div className="space-y-1.5">
              <label className="text-[13px] font-medium text-[var(--text-primary)]">{t('auth.emailLabel')}</label>
              <div className={cn('flex h-12 items-center gap-2.5 rounded-btn border bg-[var(--card-bg)] px-4 focus-within:ring-2 focus-within:ring-primary', errors.email ? 'border-danger' : 'border-[var(--border)]')}>
                <Mail className="h-[18px] w-[18px] shrink-0 text-[#94A3B8]" />
                <input
                  type="email"
                  placeholder={t('auth.emailPlaceholder')}
                  className="h-full w-full bg-transparent text-sm outline-none placeholder:text-[#94A3B8]"
                  {...register('email', {
                    required: t('auth.emailRequired'),
                    pattern: { value: /^[^@\s]+@[^@\s]+\.[^@\s]+$/, message: t('auth.emailInvalid') },
                  })}
                />
              </div>
              {errors.email && <p className="text-xs text-danger">{errors.email.message}</p>}
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label className="text-[13px] font-medium text-[var(--text-primary)]">{t('auth.passwordLabel')}</label>
              <div className={cn('flex h-12 items-center gap-2.5 rounded-btn border bg-[var(--card-bg)] px-4 focus-within:ring-2 focus-within:ring-primary', errors.password ? 'border-danger' : 'border-[var(--border)]')}>
                <Lock className="h-[18px] w-[18px] shrink-0 text-[#94A3B8]" />
                <input
                  type={showPassword ? 'text' : 'password'}
                  placeholder={t('auth.passwordPlaceholder')}
                  className="h-full w-full bg-transparent text-sm outline-none placeholder:text-[#94A3B8]"
                  {...register('password', { required: t('auth.passwordRequired') })}
                />
                <button type="button" onClick={() => setShowPassword((v) => !v)} className="text-[#94A3B8]" aria-label={t('auth.showPassword')}>
                  {showPassword ? <EyeOff className="h-[18px] w-[18px]" /> : <Eye className="h-[18px] w-[18px]" />}
                </button>
              </div>
              {errors.password && <p className="text-xs text-danger">{errors.password.message}</p>}
            </div>
          </div>

          {/* Options */}
          <div className="flex items-center justify-between">
            <label className="flex cursor-pointer items-center gap-2">
              <input type="checkbox" className="h-[18px] w-[18px] rounded accent-primary" {...register('remember')} />
              <span className="text-[13px] font-medium text-[var(--text-primary)]">{t('auth.rememberMe')}</span>
            </label>
          </div>

          {serverError && <p className="text-sm text-danger">{serverError}</p>}

          {/* Submit */}
          <button
            type="submit"
            disabled={isDisabled}
            className="flex h-12 w-full items-center justify-center gap-2 rounded-btn bg-primary text-[15px] font-semibold text-white shadow-[0_4px_14px_rgba(99,102,241,0.25)] transition-colors hover:bg-primary-hover disabled:pointer-events-none disabled:opacity-60"
          >
            {login.isPending ? t('auth.loggingIn') : countdown > 0 ? t('auth.waitingCountdown', { seconds: countdown }) : (
              <>
                {t('auth.loginButton')}
                <ArrowRight className="h-[18px] w-[18px]" />
              </>
            )}
          </button>
        </form>

        <p className="mt-12 text-center text-xs text-[var(--text-secondary)]">
          {t('auth.copyright')}
        </p>
      </div>
    </div>
  );
}
