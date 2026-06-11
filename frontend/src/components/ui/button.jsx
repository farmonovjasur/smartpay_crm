import { cva } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-primary text-white hover:bg-primary-hover',
        destructive: 'bg-danger text-white hover:bg-danger-text',
        outline: 'border border-[var(--border)] bg-[var(--card-bg)] hover:bg-[var(--hover-bg)]',
        ghost: 'hover:bg-[var(--hover-bg)]',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-10 px-4 py-2 rounded-btn',
        sm: 'h-8 px-3 text-xs rounded-btn',
        lg: 'h-11 px-6 rounded-btn',
        icon: 'h-10 w-10 rounded-btn',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  }
);

function Button({ className, variant, size, ...props }) {
  return (
    <button className={cn(buttonVariants({ variant, size, className }))} {...props} />
  );
}

export { Button, buttonVariants };
