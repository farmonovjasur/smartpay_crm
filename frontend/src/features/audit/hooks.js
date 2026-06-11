import { useQuery } from '@tanstack/react-query';
import { auditApi, normalizeAuditLog } from './api';
import { normalizePage } from '@/lib/pagination';

/**
 * Audit loglar ro'yxati (faqat admin).
 * @param {{ page?: number, pageSize?: number, entity_type?: string, user_id?: number, from?: string, to?: string }} params
 * @param {{ enabled?: boolean }} [options]
 */
export function useAuditLogs(params = {}, options = {}) {
  return useQuery({
    queryKey: ['audit-logs', params],
    queryFn: () =>
      auditApi.list(params).then((res) => {
        const page = normalizePage(res);
        return { ...page, data: page.data.map(normalizeAuditLog) };
      }),
    enabled: options.enabled ?? true,
    placeholderData: (prev) => prev,
  });
}
