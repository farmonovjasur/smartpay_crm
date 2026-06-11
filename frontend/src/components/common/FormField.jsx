import { cn } from '@/lib/utils';

/**
 * @param {{ label?: string, error?: string, children: React.ReactNode, className?: string, required?: boolean }} props
 */
export function FormField({ label, error, children, className, required }) {
  return (
    <div className={cn('space-y-1', className)}>
      {label && (
        <label className="text-sm font-medium">
          {label}
          {required && <span className="text-danger ml-0.5">*</span>}
        </label>
      )}
      {children}
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
}
