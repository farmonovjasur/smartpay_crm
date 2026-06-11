import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Backend Notification javobini snake_case'ga normallashtiradi.
 * @param {any} n
 */
export function normalizeNotification(n) {
  if (!n || typeof n !== 'object') return n;
  return {
    id: n.id,
    type: n.type ?? '',
    title: n.title ?? '',
    message: n.message ?? '',
    is_read: n.is_read ?? n.isRead ?? false,
    created_at: n.created_at ?? n.createdAt ?? null,
    read_at: n.read_at ?? n.readAt ?? null,
  };
}

/**
 * O'qilmagan bildirishnomalar sonini hisoblaydi.
 * @param {Array<{is_read: boolean}>} list
 * @returns {number}
 */
export function countUnread(list) {
  if (!Array.isArray(list)) return 0;
  let n = 0;
  for (const it of list) {
    if (it && it.is_read === false) n += 1;
  }
  return n;
}

export const notificationsApi = {
  list: (filters) =>
    api.get('/notifications', { params: buildListParams(filters) }).then((r) => r.data),
  markRead: (id) => api.post(`/notifications/${id}/read`).then((r) => r.data),
  markAllRead: () => api.post('/notifications/read-all').then((r) => r.data),
  delete: (id) => api.delete(`/notifications/${id}`).then((r) => r.data),
  deleteAllRead: () => api.post('/notifications/delete-all-read').then((r) => r.data),
};
