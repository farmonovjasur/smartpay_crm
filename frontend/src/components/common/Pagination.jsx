import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { computeTotalPages } from '@/lib/pagination';

/**
 * @param {{ page: number, total: number, pageSize: number, onPageChange: (p: number) => void, className?: string }} props
 */
export function Pagination({ page, total, pageSize, onPageChange, className }) {
  const totalPages = computeTotalPages(total, pageSize);
  if (totalPages <= 1) return null;

  const pages = getVisiblePages(page, totalPages);

  return (
    <nav className={cn('flex items-center gap-1', className)} aria-label="Sahifalar">
      <Button variant="outline" size="icon" disabled={page <= 1} onClick={() => onPageChange(page - 1)} aria-label="Oldingi">
        <ChevronLeft className="h-4 w-4" />
      </Button>
      {pages.map((p, i) =>
        p === '...' ? (
          <span key={`dot-${i}`} className="px-2 text-[var(--text-secondary)]">…</span>
        ) : (
          <Button key={p} variant={p === page ? 'default' : 'outline'} size="sm" onClick={() => onPageChange(p)}>
            {p}
          </Button>
        )
      )}
      <Button variant="outline" size="icon" disabled={page >= totalPages} onClick={() => onPageChange(page + 1)} aria-label="Keyingi">
        <ChevronRight className="h-4 w-4" />
      </Button>
    </nav>
  );
}

function getVisiblePages(current, total) {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  if (current <= 3) return [1, 2, 3, 4, '...', total];
  if (current >= total - 2) return [1, '...', total - 3, total - 2, total - 1, total];
  return [1, '...', current - 1, current, current + 1, '...', total];
}
