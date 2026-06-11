import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Backend User javobini snake_case'ga normallashtiradi.
 * R18.1 kelishuv: `name`, `email`, `role`, `isActive`, `lastLoginAt` qaytadi (camelCase).
 * @param {any} u
 */
export function normalizeUser(u) {
  if (!u || typeof u !== 'object') return u;
  return {
    id: u.id,
    name: u.name ?? '',
    email: u.email ?? '',
    role: u.role ?? 'user',
    is_active: u.is_active ?? u.isActive ?? true,
    last_login_at: u.last_login_at ?? u.lastLoginAt ?? null,
    created_at: u.created_at ?? u.createdAt ?? null,
  };
}

export const usersApi = {
  list: (filters) => api.get('/users', { params: buildListParams(filters) }).then((r) => r.data),
  get: (id) => api.get(`/users/${id}`).then((r) => r.data),
  create: (body) => api.post('/users', body).then((r) => r.data),
  update: (id, body) => api.put(`/users/${id}`, body).then((r) => r.data),
  remove: (id) => api.delete(`/users/${id}`),
  resetPassword: (id) => api.post(`/users/${id}/reset-password`).then((r) => r.data),
};
