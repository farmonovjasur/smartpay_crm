import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

export function LoadingState({ className, text = 'Yuklanmoqda…' }) {
  return (
    <div className={cn('flex flex-col items-center justify-center py-12 text-[var(--text-secondary)]', className)}>
      <Loader2 className="h-8 w-8 animate-spin mb-2" />
      <p className="text-sm">{text}</p>
    </div>
  );
}
