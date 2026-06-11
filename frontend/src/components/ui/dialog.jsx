import { createContext, useContext, useState } from 'react';
import { cn } from '@/lib/utils';

const DialogContext = createContext({ open: false, onOpenChange: () => {} });

function Dialog({ open, onOpenChange, children }) {
  const [internalOpen, setInternalOpen] = useState(false);
  const isControlled = open !== undefined;
  const isOpen = isControlled ? open : internalOpen;
  const setOpen = isControlled ? onOpenChange : setInternalOpen;
  return <DialogContext.Provider value={{ open: isOpen, onOpenChange: setOpen }}>{children}</DialogContext.Provider>;
}

function DialogTrigger({ children, asChild, ...props }) {
  const { onOpenChange } = useContext(DialogContext);
  return <button onClick={() => onOpenChange(true)} {...props}>{children}</button>;
}

function DialogContent({ className, children, ...props }) {
  const { open, onOpenChange } = useContext(DialogContext);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="fixed inset-0 bg-black/50" onClick={() => onOpenChange(false)} />
      <div className={cn('relative z-50 w-full max-w-lg rounded-card bg-[var(--card-bg)] p-6 shadow-lg', className)} {...props}>
        {children}
      </div>
    </div>
  );
}

function DialogHeader({ className, ...props }) {
  return <div className={cn('flex flex-col space-y-1.5 text-center sm:text-left', className)} {...props} />;
}

function DialogTitle({ className, ...props }) {
  return <h2 className={cn('text-lg font-semibold', className)} {...props} />;
}

function DialogDescription({ className, ...props }) {
  return <p className={cn('text-sm text-[var(--text-secondary)]', className)} {...props} />;
}

export { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription };
