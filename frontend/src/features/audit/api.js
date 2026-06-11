import api from '@/lib/api';
import { buildListParams } from '@/lib/queryParams';

/**
 * Backend AuditLog javobini snake_case'ga normallashtiradi.
 * @param {any} l
 */
export function normalizeAuditLog(l) {
  if (!l || typeof l !== 'object') return l;
  return {
    id: l.id,
    user_id: l.user_id ?? l.userId ?? null,
    user_name: l.user_name ?? l.userName ?? l.user?.name ?? null,
    user_email: l.user_email ?? l.userEmail ?? l.user?.email ?? null,
    action: l.action ?? '',
    entity_type: l.entity_type ?? l.entityType ?? '',
    entity_id: l.entity_id ?? l.entityId ?? null,
    entity_label: l.entity_label ?? l.entityLabel ?? null,
    details: l.details ?? null,
    ip: l.ip ?? null,
    user_agent: l.user_agent ?? l.userAgent ?? null,
    created_at: l.created_at ?? l.createdAt ?? null,
  };
}

export const auditApi = {
  /**
   * @param {{ page?: number, pageSize?: number, entity_type?: string, user_id?: number, from?: string, to?: string }} params
   */
  list: (params) =>
    api.get('/audit-logs', { params: buildListParams(params) }).then((r) => r.data),
};
