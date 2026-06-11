import { cn } from '@/lib/utils';

function Card({ className, ...props }) {
  return <div className={cn('rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm', className)} {...props} />;
}

function CardHeader({ className, ...props }) {
  return <div className={cn('flex flex-col space-y-1.5 p-6', className)} {...props} />;
}

function CardTitle({ className, ...props }) {
  return <h3 className={cn('text-lg font-semibold leading-none', className)} {...props} />;
}

function CardContent({ className, ...props }) {
  return <div className={cn('p-6 pt-0', className)} {...props} />;
}

export { Card, CardHeader, CardTitle, CardContent };
