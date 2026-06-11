import { useMemo, useState } from 'react';
import {
  Bell, BellRing, CheckCheck, AlertTriangle, FileText, Users, Wallet, Info, Trash2,
} from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState, LoadingState,
} from '@/components/common';
import { useNotifications, useMarkRead, useMarkAllRead, useDeleteNotification, useDeleteAllRead } from './hooks';
import { showSuccess } from '@/lib/toast';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';

/** Bildirishnoma turi → ikonka + rang sxemasi (backend enum qiymatlariga mos). */
const TYPE_META = {
  debt_created: { icon: AlertTriangle, bg: 'bg-danger-bg', color: 'text-danger' },
  invoice_generated: { icon: FileText, bg: 'bg-primary-bg', color: 'text-primary' },
  client_imported: { icon: Users, bg: 'bg-success-bg', color: 'text-success' },
  system: { icon: Info, bg: 'bg-info-bg', color: 'text-info' },
  default: { icon: Info, bg: 'bg-bg-light', color: 'text-[var(--text-secondary)]' },
};

function metaFor(type) {
  return TYPE_META[type] || TYPE_META.default;
}

export default function NotificationsPage() {
  const [unreadOnly, setUnreadOnly] = useState(false);
  const [page, setPage] = useState(1);

  const filters = useMemo(
    () => ({ page, ...(unreadOnly ? { unread_only: true } : {}) }),
    [page, unreadOnly]
  );

  const { data, isLoading, isError, refetch } = useNotifications(filters);
  const markRead = useMarkRead();
  const markAllRead = useMarkAllRead();
  const deleteNotification = useDeleteNotification();
  const deleteAllRead = useDeleteAllRead();

  const items = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;

  function toggleUnreadOnly() {
    setUnreadOnly((v) => !v);
    setPage(1);
  }

  function handleMarkRead(id) {
    markRead.mutate(id);
  }

  function handleDelete(id) {
    deleteNotification.mutate(id);
  }

  function handleDeleteAllRead() {
    deleteAllRead.mutate(undefined, {
      onSuccess: (res) => {
        const count = res?.deletedCount ?? 0;
        showSuccess(
          count > 0
            ? `${count} ta o'qilgan bildirishnoma o'chirildi`
            : "O'chiradigan bildirishnoma yo'q"
        );
      },
    });
  }

  function handleMarkAllRead() {
    markAllRead.mutate(undefined, {
      onSuccess: (res) => {
        const count = res?.markedCount ?? res?.marked_count ?? 0;
        showSuccess(
          count > 0
            ? `${count} ta bildirishnoma o'qildi deb belgilandi`
            : "Yangi o'qilmagan bildirishnoma yo'q"
        );
      },
    });
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Bildirishnomalar"
        count={total || undefined}
        actions={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleDeleteAllRead}
              disabled={deleteAllRead.isPending}
              className="flex items-center gap-2 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-4 py-2.5 text-sm font-medium text-danger transition-colors hover:bg-danger-bg disabled:opacity-60"
            >
              <Trash2 className="h-4 w-4" />
              {deleteAllRead.isPending ? "O'chirilmoqda…" : "O'qilganlarni o'chirish"}
            </button>
            <button
              type="button"
              onClick={handleMarkAllRead}
              disabled={markAllRead.isPending}
              className="flex items-center gap-2 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-4 py-2.5 text-sm font-medium text-[var(--text-primary)] transition-colors hover:bg-bg-light disabled:opacity-60"
            >
              <CheckCheck className="h-4 w-4" />
              {markAllRead.isPending ? 'Belgilanmoqda…' : "Hammasini o'qildi"}
            </button>
          </div>
        }
      />

      {/* Filter toggle */}
      <div className="flex items-center gap-3">
        <label className="inline-flex cursor-pointer items-center gap-2 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-4 py-2.5 text-sm">
          <input
            type="checkbox"
            checked={unreadOnly}
            onChange={toggleUnreadOnly}
            className="h-4 w-4 rounded border-[var(--border)] text-primary focus:ring-primary"
          />
          <span className="font-medium text-[var(--text-primary)]">Faqat o'qilmaganlar</span>
        </label>
      </div>

      {/* List */}
      <div className="rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        {isLoading ? (
          <LoadingState />
        ) : items.length === 0 ? (
          <EmptyState
            icon={Bell}
            title={unreadOnly ? "O'qilmagan bildirishnoma yo'q" : "Bildirishnomalar yo'q"}
            description={
              unreadOnly
                ? 'Barcha bildirishnomalar o‘qilgan'
                : "Yangi bildirishnomalar shu yerda paydo bo'ladi"
            }
          />
        ) : (
          <ul className="divide-y divide-[var(--border)]">
            {items.map((n) => (
              <NotificationItem key={n.id} notification={n} onMarkRead={handleMarkRead} onDelete={handleDelete} />
            ))}
          </ul>
        )}

        {/* Pagination */}
        {data && total > 0 && (
          <div className="flex items-center justify-between border-t border-[var(--border)] px-5 py-4">
            <span className="text-[13px] text-[var(--text-secondary)]">Jami: {total} ta</span>
            <Pagination
              page={data.page}
              total={total}
              pageSize={pageSize}
              onPageChange={setPage}
            />
          </div>
        )}
      </div>
    </div>
  );
}

function NotificationItem({ notification, onMarkRead, onDelete }) {
  const meta = metaFor(notification.type);
  const Icon = notification.is_read ? Bell : meta.icon;
  const unread = !notification.is_read;

  return (
    <li
      className={cn(
        'flex items-start gap-4 px-5 py-4 transition-colors',
        unread && 'bg-primary-bg/30'
      )}
    >
      <span
        className={cn(
          'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
          unread ? meta.bg : 'bg-bg-light'
        )}
      >
        <Icon className={cn('h-5 w-5', unread ? meta.color : 'text-[var(--text-secondary)]')} />
      </span>

      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex items-start justify-between gap-3">
          <p
            className={cn(
              'text-sm leading-tight',
              unread ? 'font-semibold text-[var(--text-primary)]' : 'text-[var(--text-secondary)]'
            )}
          >
            {notification.title}
          </p>
          <div className="flex shrink-0 items-center gap-2">
            <time className="text-xs text-[var(--text-secondary)]">
              {formatDate(notification.created_at)}
            </time>
            <button
              type="button"
              onClick={() => onDelete(notification.id)}
              className="rounded p-1 text-[var(--text-secondary)] hover:bg-danger-bg hover:text-danger"
              title="O'chirish"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        {notification.message && (
          <p className="text-sm text-[var(--text-secondary)]">{notification.message}</p>
        )}

        {unread && (
          <button
            type="button"
            onClick={() => onMarkRead(notification.id)}
            className="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary-hover"
          >
            <BellRing className="h-3.5 w-3.5" />
            O'qildi deb belgilash
          </button>
        )}
      </div>
    </li>
  );
}
