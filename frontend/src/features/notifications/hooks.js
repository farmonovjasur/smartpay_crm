import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { notificationsApi, normalizeNotification } from './api';
import { normalizePage } from '@/lib/pagination';

const KEYS = {
  all: ['notifications'],
  list: (filters) => ['notifications', 'list', filters],
  unreadCount: ['notifications', 'unreadCount'],
};

export function useNotifications(filters) {
  return useQuery({
    queryKey: KEYS.list(filters),
    queryFn: () =>
      notificationsApi.list(filters).then((res) => {
        const page = normalizePage(res);
        return { ...page, data: page.data.map(normalizeNotification) };
      }),
    placeholderData: (prev) => prev,
  });
}

/**
 * Navbar badge uchun o'qilmaganlar soni.
 * Backend alohida count endpoint'ini taqdim etmagani uchun
 * `unread_only=true&page=1` so'rov natijasidagi `total` dan olamiz.
 */
export function useUnreadCount() {
  return useQuery({
    queryKey: KEYS.unreadCount,
    queryFn: () =>
      notificationsApi.list({ unread_only: true, page: 1 }).then((res) => {
        const page = normalizePage(res);
        return page.total;
      }),
    // 30s avtomatik yangilash — yangi bildirishnomalarni ushlash uchun.
    refetchInterval: 30_000,
    refetchOnWindowFocus: true,
  });
}

export function useMarkRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id) => notificationsApi.markRead(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useMarkAllRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useDeleteNotification() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id) => notificationsApi.delete(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}

export function useDeleteAllRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => notificationsApi.deleteAllRead(),
    onSuccess: () => qc.invalidateQueries({ queryKey: KEYS.all }),
  });
}
