import { Inbox } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export function EmptyState({ icon: Icon = Inbox, title, description, action, className }) {
  const t = useT();
  return (
    <div className={cn('flex flex-col items-center justify-center py-12 text-[var(--text-secondary)]', className)}>
      <Icon className="h-12 w-12 mb-3 opacity-40" />
      <p className="font-medium">{title || t('common.noData')}</p>
      {description && <p className="text-sm mt-1">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
