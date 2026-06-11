import { useLocale } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * UZ/RU til almashtirgich — pill toggle dizayni (dizaynga mos).
 */
export function LanguageSwitcher({ className }) {
  const { locale, setLocale } = useLocale();

  return (
    <div
      className={cn(
        'flex items-center rounded-full border border-[var(--border)] bg-[var(--bg-light)] p-1',
        className
      )}
      role="radiogroup"
      aria-label="Tilni tanlash"
    >
      <button
        type="button"
        role="radio"
        aria-checked={locale === 'uz'}
        onClick={() => setLocale('uz')}
        className={cn(
          'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
          locale === 'uz'
            ? 'bg-primary text-white'
            : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
        )}
      >
        UZ
      </button>
      <button
        type="button"
        role="radio"
        aria-checked={locale === 'ru'}
        onClick={() => setLocale('ru')}
        className={cn(
          'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
          locale === 'ru'
            ? 'bg-primary text-white'
            : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]'
        )}
      >
        RU
      </button>
    </div>
  );
}
