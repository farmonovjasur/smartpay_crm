import { cn } from '@/lib/utils';

function Table({ className, ...props }) {
  return <div className="relative w-full overflow-auto"><table className={cn('w-full caption-bottom text-sm', className)} {...props} /></div>;
}

function TableHeader({ className, ...props }) {
  return <thead className={cn('[&_tr]:border-b', className)} {...props} />;
}

function TableBody({ className, ...props }) {
  return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

function TableRow({ className, ...props }) {
  return <tr className={cn('border-b border-[var(--border)] transition-colors hover:bg-[var(--hover-bg)]', className)} {...props} />;
}

function TableHead({ className, ...props }) {
  return <th className={cn('h-12 px-4 text-left align-middle font-medium text-[var(--text-secondary)]', className)} {...props} />;
}

function TableCell({ className, ...props }) {
  return <td className={cn('p-4 align-middle', className)} {...props} />;
}

export { Table, TableHeader, TableBody, TableRow, TableHead, TableCell };
