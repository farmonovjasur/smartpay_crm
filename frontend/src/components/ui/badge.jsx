import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
  {
    variants: {
      variant: {
        default: 'bg-primary-bg text-primary-text',
        success: 'bg-success-bg text-success-text',
        warning: 'bg-warning-bg text-warning-text',
        danger: 'bg-danger-bg text-danger-text',
        info: 'bg-info-bg text-info-text',
        purple: 'bg-purple-bg text-purple-text',
        teal: 'bg-teal-bg text-teal-text',
      },
    },
    defaultVariants: { variant: 'default' },
  }
);

function Badge({ className, variant, ...props }) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
