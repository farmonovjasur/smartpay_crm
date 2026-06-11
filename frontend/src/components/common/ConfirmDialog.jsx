import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';

/**
 * @param {{ open: boolean, onOpenChange: (v: boolean) => void, title?: string, description?: string, confirmLabel?: string, variant?: 'default'|'destructive', loading?: boolean, onConfirm: () => void }} props
 */
export function ConfirmDialog({ open, onOpenChange, title, description, confirmLabel, variant = 'destructive', loading, onConfirm }) {
  const t = useT();
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title || t('common.confirm')}</DialogTitle>
          {description && <DialogDescription>{description}</DialogDescription>}
        </DialogHeader>
        <div className="flex justify-end gap-2 pt-4">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>{t('common.cancel')}</Button>
          <Button variant={variant} onClick={onConfirm} disabled={loading}>
            {loading ? t('common.waiting') : (confirmLabel || t('common.yes'))}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
