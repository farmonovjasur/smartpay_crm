import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function ErrorState({ message, onRetry, className }) {
  const t = useT();
  return (
    <div className={cn('flex flex-col items-center justify-center py-12 text-[var(--text-secondary)]', className)}>
      <AlertTriangle className="h-12 w-12 mb-3 text-danger opacity-60" />
      <p className="font-medium">{message || t('errors.unknown')}</p>
      {onRetry && (
        <Button variant="outline" size="sm" className="mt-4" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      )}
    </div>
  );
}
