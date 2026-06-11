import {
  UserPlus, UserCog, UserX, Pencil, Trash2, FilePlus, FileX,
  Banknote, CalendarCheck, Upload, KeyRound, Activity,
} from 'lucide-react';
import { useT } from '@/lib/i18n';
import { useCallback } from 'react';

/**
 * Action → icon + color mapping (til-mustaqil).
 * @type {Record<string, { icon: any, color: string }>}
 */
const ACTION_ICONS = {
  'client.created': { icon: UserPlus, color: 'text-success' },
  'client.updated': { icon: Pencil, color: 'text-info' },
  'client.deleted': { icon: UserX, color: 'text-danger' },
  'client.import': { icon: Upload, color: 'text-primary' },
  'client.mark_paid': { icon: CalendarCheck, color: 'text-success' },
  'invoice.generated': { icon: FilePlus, color: 'text-primary' },
  'invoice.deleted': { icon: FileX, color: 'text-danger' },
  'debt.paid': { icon: Banknote, color: 'text-success' },
  'user.created': { icon: UserPlus, color: 'text-success' },
  'user.updated': { icon: UserCog, color: 'text-info' },
  'user.deleted': { icon: UserX, color: 'text-danger' },
  'user.password_reset': { icon: KeyRound, color: 'text-warning-text' },
};

/**
 * Hook — tarjima bilan action meta qaytaradi.
 * @returns {(action: string) => { label: string, icon: any, color: string }}
 */
export function useActionMeta() {
  const t = useT();
  return useCallback((action) => {
    const meta = ACTION_ICONS[action] || { icon: Activity, color: 'text-[var(--text-secondary)]' };
    const label = t(`audit.actions.${action}`) !== `audit.actions.${action}`
      ? t(`audit.actions.${action}`)
      : t('audit.actions.default');
    return { ...meta, label };
  }, [t]);
}

/**
 * Statik versiya (hook tashqarisida ishlatish uchun — deprecated, lekin backward compat).
 * @param {string} action
 * @returns {{ label: string, icon: any, color: string }}
 */
export function actionMeta(action) {
  const meta = ACTION_ICONS[action] || { icon: Activity, color: 'text-[var(--text-secondary)]' };
  // Statik holatda — o'zbek
  const labels = {
    'client.created': "Mijoz qo'shildi",
    'client.updated': 'Mijoz tahrirlandi',
    'client.deleted': "Mijoz o'chirildi",
    'client.import': 'Mijozlar import qilindi',
    'client.mark_paid': "Oylik to'lov belgilandi",
    'invoice.generated': 'Faktura yaratildi',
    'invoice.deleted': "Faktura o'chirildi",
    'debt.paid': "Qarz to'landi",
    'user.created': "Foydalanuvchi qo'shildi",
    'user.updated': 'Foydalanuvchi tahrirlandi',
    'user.deleted': "Foydalanuvchi o'chirildi",
    'user.password_reset': 'Parol tiklandi',
  };
  return { ...meta, label: labels[action] || action || 'Amal' };
}
