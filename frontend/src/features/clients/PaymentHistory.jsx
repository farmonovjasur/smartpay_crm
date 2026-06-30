import { History, Banknote, CreditCard, CalendarDays, AlertTriangle } from 'lucide-react';
import { usePayments } from './hooks';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';

export function PaymentHistory({ clientId }) {
  const { data: history = [], isLoading } = usePayments(clientId);

  if (isLoading) {
    return (
      <div className="flex animate-pulse flex-col space-y-4 rounded-xl border border-[var(--border)] bg-[var(--card-bg)] p-5">
        <div className="h-5 w-32 rounded bg-bg-light"></div>
        <div className="space-y-3">
          <div className="h-10 w-full rounded bg-bg-light"></div>
          <div className="h-10 w-full rounded bg-bg-light"></div>
        </div>
      </div>
    );
  }

  if (history.length === 0) {
    return (
      <div className="rounded-xl border border-[var(--border)] bg-[var(--card-bg)] p-8 text-center">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-bg-light text-[var(--text-secondary)]">
          <History className="h-6 w-6" />
        </div>
        <h3 className="mt-4 text-sm font-medium text-[var(--text-primary)]">
          To'lovlar tarixi mavjud emas
        </h3>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">
          Mijozning to'lovlar tarixi hali shakllanmagan.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-[var(--border)] bg-[var(--card-bg)] overflow-hidden">
      <div className="border-b border-[var(--border)] px-5 py-4">
        <h3 className="text-base font-semibold text-[var(--text-primary)]">
          Umumiy to'lovlar tarixi
        </h3>
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="bg-bg-light text-[var(--text-secondary)]">
            <tr>
              <th className="px-5 py-3 font-medium">Sana</th>
              <th className="px-5 py-3 font-medium">Davr</th>
              <th className="px-5 py-3 font-medium">Summa</th>
              <th className="px-5 py-3 font-medium">Turi</th>
              <th className="px-5 py-3 font-medium">Usul</th>
              <th className="px-5 py-3 font-medium">Kiritgan xodim</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {history.map((row) => (
              <tr key={row.id} className="hover:bg-bg-light/50">
                <td className="px-5 py-3 text-[var(--text-primary)] whitespace-nowrap">
                  {formatDate(row.paid_at)}
                </td>
                <td className="px-5 py-3 font-medium text-[var(--text-primary)] whitespace-nowrap">
                  {formatPeriod(row.period)}
                </td>
                <td className="px-5 py-3 font-medium text-success-text whitespace-nowrap">
                  {formatMoney(row.amount)} UZS
                </td>
                <td className="px-5 py-3 whitespace-nowrap">
                  {row.is_debt ? (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-warning-bg px-2 py-1 text-xs font-medium text-warning-text">
                      <AlertTriangle className="h-3.5 w-3.5" />
                      Qarz to'lovi
                    </div>
                  ) : (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-primary-bg px-2 py-1 text-xs font-medium text-primary">
                      <CalendarDays className="h-3.5 w-3.5" />
                      Oylik to'lov
                    </div>
                  )}
                </td>
                <td className="px-5 py-3 whitespace-nowrap">
                  {row.method === 'fakt' ? (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-bg-light px-2 py-1 text-xs font-medium text-[var(--text-secondary)]">
                      <CreditCard className="h-3.5 w-3.5" />
                      Fakt
                    </div>
                  ) : (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-bg-light px-2 py-1 text-xs font-medium text-[var(--text-secondary)]">
                      <Banknote className="h-3.5 w-3.5" />
                      Naqt
                    </div>
                  )}
                </td>
                <td className="px-5 py-3 text-[var(--text-secondary)] whitespace-nowrap">
                  {row.created_by || 'Tizim'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
