import { useMemo, useState } from 'react';
import { Filter, ChevronDown, Hash, Globe, Monitor, User, X } from 'lucide-react';
import {
  PageHeader, Pagination, ErrorState, EmptyState,
} from '@/components/common';
import { useAuditLogs } from './hooks';
import { useUsers } from '@/features/users/hooks';
import { actionMeta } from './actionMeta';
import { formatDate } from '@/lib/date';
import { cn } from '@/lib/utils';

const ENTITY_TYPES = [
  { value: '', label: 'Barchasi' },
  { value: 'client', label: 'Mijoz' },
  { value: 'invoice', label: 'Faktura' },
  { value: 'debt', label: 'Qarz' },
  { value: 'user', label: 'Foydalanuvchi' },
  { value: 'notification', label: 'Bildirishnoma' },
];

export default function AuditLogPage() {
  const [entityType, setEntityType] = useState('');
  const [userId, setUserId] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [page, setPage] = useState(1);

  const filters = useMemo(
    () => ({
      page,
      ...(entityType ? { entity_type: entityType } : {}),
      ...(userId ? { user_id: Number(userId) } : {}),
      ...(fromDate ? { from: fromDate } : {}),
      ...(toDate ? { to: toDate } : {}),
    }),
    [page, entityType, userId, fromDate, toDate]
  );

  const { data, isLoading, isError, refetch } = useAuditLogs(filters);
  // Foydalanuvchi filtri uchun ro'yxat (admin uchun mavjud).
  const { data: usersData } = useUsers({ pageSize: 100 });

  const rows = data?.data || [];
  const total = data?.total ?? 0;
  const pageSize = data?.pageSize ?? 20;
  const startIdx = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const endIdx = Math.min(page * pageSize, total);
  const hasFilters = !!(entityType || userId || fromDate || toDate);

  function changeFilter(setter) {
    return (e) => {
      setter(e.target.value);
      setPage(1);
    };
  }

  function clearFilters() {
    setEntityType('');
    setUserId('');
    setFromDate('');
    setToDate('');
    setPage(1);
  }

  if (isError) return <ErrorState onRetry={refetch} />;

  return (
    <div className="space-y-6">
      <PageHeader title="Audit log" count={total || undefined} />

      {/* Filter bar */}
      <div className="flex flex-wrap items-end gap-3 rounded-card border border-[var(--border)] bg-[var(--card-bg)] p-4 shadow-sm">
        <FilterField label="Obyekt turi" icon={Filter}>
          <Select value={entityType} onChange={changeFilter(setEntityType)} className="w-[160px]">
            {ENTITY_TYPES.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </Select>
        </FilterField>

        <FilterField label="Foydalanuvchi">
          <Select value={userId} onChange={changeFilter(setUserId)} className="w-[200px]">
            <option value="">Barchasi</option>
            {(usersData?.data || []).map((u) => (
              <option key={u.id} value={u.id}>
                {u.name} ({u.email})
              </option>
            ))}
          </Select>
        </FilterField>

        <FilterField label="Boshlanish sanasi">
          <input
            type="date"
            value={fromDate}
            onChange={changeFilter(setFromDate)}
            className="h-11 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
        </FilterField>

        <FilterField label="Tugash sanasi">
          <input
            type="date"
            value={toDate}
            onChange={changeFilter(setToDate)}
            className="h-11 rounded-btn border border-[var(--border)] bg-[var(--card-bg)] px-3 text-sm outline-none focus:ring-2 focus:ring-primary"
          />
        </FilterField>

        {hasFilters && (
          <button
            type="button"
            onClick={clearFilters}
            className="flex h-11 items-center gap-1.5 rounded-btn border border-[var(--border)] bg-bg-light px-4 text-sm font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)]"
          >
            <X className="h-4 w-4" />
            Filtrni tozalash
          </button>
        )}
      </div>

      {/* Table */}
      <div className="overflow-hidden rounded-card border border-[var(--border)] bg-[var(--card-bg)] shadow-sm">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-[var(--border)] bg-bg-light text-left text-[11px] font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                <th className="w-12 px-5 py-3.5">#</th>
                <th className="px-3 py-3.5">Amal</th>
                <th className="px-3 py-3.5">Obyekt</th>
                <th className="px-3 py-3.5">Foydalanuvchi</th>
                <th className="px-3 py-3.5 min-w-[180px]">IP</th>
                <th className="px-3 py-3.5">Sana</th>
              </tr>
            </thead>
            <tbody>
              {isLoading ? (
                <tr>
                  <td colSpan={6} className="py-16 text-center text-[var(--text-secondary)]">
                    Yuklanmoqda…
                  </td>
                </tr>
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={6} className="py-16 text-center">
                    <EmptyState
                      title="Audit yozuvlari topilmadi"
                      description={hasFilters ? 'Filtrni o\'zgartiring yoki tozalang' : "Hozircha jurnal yozuvlari yo'q"}
                    />
                  </td>
                </tr>
              ) : (
                rows.map((log, i) => (
                  <ActionRow key={log.id} log={log} index={startIdx + i} alt={i % 2 === 1} />
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex items-center justify-between border-t border-[var(--border)] px-5 py-4">
          <span className="text-[13px] text-[var(--text-secondary)]">
            {startIdx}–{endIdx} / {total} ta
          </span>
          {data && total > 0 && (
            <Pagination page={data.page} total={total} pageSize={pageSize} onPageChange={setPage} />
          )}
        </div>
      </div>
    </div>
  );
}

function ActionRow({ log, index, alt }) {
  const meta = actionMeta(log.action);
  const Icon = meta.icon;
  return (
    <tr className={cn('border-b border-[var(--border)] last:border-0', alt && 'bg-bg-light')}>
      <td className="px-5 py-3.5 text-[var(--text-secondary)]">{index}</td>
      <td className="px-3 py-3.5">
        <div className="flex items-center gap-2.5">
          <span className={cn('flex h-8 w-8 items-center justify-center rounded-md bg-bg-light', meta.color)}>
            <Icon className="h-4 w-4" />
          </span>
          <div className="space-y-0.5">
            <p className="text-[13px] font-medium text-[var(--text-primary)]">{meta.label}</p>
            <p className="font-mono text-[10px] text-[var(--text-secondary)]">{log.action}</p>
          </div>
        </div>
      </td>
      <td className="px-3 py-3.5 text-[var(--text-primary)]">
        <div className="space-y-0.5">
          <span className="capitalize">{log.entity_type || '—'}</span>
          {log.entity_id != null && (
            <span className="flex items-center gap-1 text-[11px] text-[var(--text-secondary)]">
              <Hash className="h-3 w-3" />
              {log.entity_id}
            </span>
          )}
          {log.entity_label && (
            <p className="max-w-[200px] truncate text-[11px] font-medium text-[var(--text-primary)]" title={log.entity_label}>
              {log.entity_label}
            </p>
          )}
        </div>
      </td>
      <td className="px-3 py-3.5 text-[var(--text-primary)]">
        <div className="space-y-0.5">
          {log.user_name ? (
            <>
              <p className="flex items-center gap-1.5 text-[13px] font-medium">
                <User className="h-3.5 w-3.5 text-[var(--text-secondary)]" />
                {log.user_name}
              </p>
              {log.user_email && (
                <p className="text-[11px] text-[var(--text-secondary)]">{log.user_email}</p>
              )}
            </>
          ) : log.user_email ? (
            <p className="text-[12px] text-[var(--text-secondary)]">{log.user_email}</p>
          ) : log.user_id != null ? (
            <span className="text-[12px] text-[var(--text-secondary)]">#{log.user_id}</span>
          ) : (
            <span className="text-[12px] text-[var(--text-secondary)]">Sistema</span>
          )}
        </div>
      </td>
      <td className="px-3 py-3.5 text-[var(--text-secondary)] min-w-[180px]">
        <div className="space-y-1">
          {log.ip ? (
            <span className="inline-flex items-center gap-1.5 font-mono text-xs" title={log.ip}>
              <Globe className="h-3.5 w-3.5 flex-shrink-0" />
              <span className="whitespace-nowrap">{formatIp(log.ip)}</span>
            </span>
          ) : (
            '—'
          )}
          {log.user_agent && (
            <p className="flex items-center gap-1 text-[10px] text-[var(--text-secondary)]" title={log.user_agent}>
              <Monitor className="h-3 w-3 flex-shrink-0" />
              {parseUserAgent(log.user_agent)}
            </p>
          )}
        </div>
      </td>
      <td className="px-3 py-3.5 text-[var(--text-secondary)]">{formatDate(log.created_at)}</td>
    </tr>
  );
}

function FilterField({ label, children }) {
  return (
    <div className="space-y-1.5">
      <span className="text-[11px] font-medium uppercase tracking-wide text-[var(--text-secondary)]">
        {label}
      </span>
      <div>{children}</div>
    </div>
  );
}

function Select({ className, children, ...props }) {
  return (
    <div className={cn('relative inline-block', className)}>
      <select
        {...props}
        className="h-11 w-full appearance-none rounded-btn border border-[var(--border)] bg-[var(--card-bg)] pl-4 pr-9 text-sm text-[var(--text-primary)] outline-none focus:ring-2 focus:ring-primary"
      >
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--text-secondary)]" />
    </div>
  );
}

/**
 * Formats IP address for display — makes localhost variants human-readable.
 */
function formatIp(ip) {
  if (!ip) return '—';
  if (ip === '::1' || ip === '127.0.0.1') return ip + ' (localhost)';
  return ip;
}

/**
 * Parses a raw User-Agent string into a short, human-readable browser/OS label.
 */
function parseUserAgent(ua) {
  if (!ua) return '';

  let browser = '';
  let os = '';

  // Detect browser
  if (ua.includes('Edg/')) browser = 'Edge';
  else if (ua.includes('OPR/') || ua.includes('Opera')) browser = 'Opera';
  else if (ua.includes('Chrome/')) browser = 'Chrome';
  else if (ua.includes('Firefox/')) browser = 'Firefox';
  else if (ua.includes('Safari/') && !ua.includes('Chrome')) browser = 'Safari';
  else browser = 'Boshqa';

  // Detect OS
  if (ua.includes('Windows')) os = 'Windows';
  else if (ua.includes('Mac OS')) os = 'macOS';
  else if (ua.includes('Linux')) os = 'Linux';
  else if (ua.includes('Android')) os = 'Android';
  else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';

  return [browser, os].filter(Boolean).join(' / ') || ua.slice(0, 30);
}
