import { History, Banknote, CreditCard } from 'lucide-react';
import { usePrepayments } from './hooks';
import { formatDate } from '@/lib/date';
import { formatMoney } from '@/lib/money';

export function PrepaymentHistory({ clientId }) {
  const { data: history = [], isLoading } = usePrepayments(clientId);

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
          Tarix mavjud emas
        </h3>
        <p className="mt-1 text-sm text-[var(--text-secondary)]">
          Mijoz hali oldindan to'lov amalga oshirmagan.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-[var(--border)] bg-[var(--card-bg)] overflow-hidden">
      <div className="border-b border-[var(--border)] px-5 py-4">
        <h3 className="text-base font-semibold text-[var(--text-primary)]">
          Oldindan to'lovlar tarixi
        </h3>
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="bg-bg-light text-[var(--text-secondary)]">
            <tr>
              <th className="px-5 py-3 font-medium">Sana</th>
              <th className="px-5 py-3 font-medium">Summa</th>
              <th className="px-5 py-3 font-medium">Usul</th>
              <th className="px-5 py-3 font-medium">Kiritgan xodim</th>
              <th className="px-5 py-3 font-medium">Izoh</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {history.map((row) => (
              <tr key={row.id} className="hover:bg-bg-light/50">
                <td className="px-5 py-3 text-[var(--text-primary)] whitespace-nowrap">
                  {formatDate(row.paid_at)}
                </td>
                <td className="px-5 py-3 font-medium text-success-text whitespace-nowrap">
                  + {formatMoney(row.amount)} UZS
                </td>
                <td className="px-5 py-3 whitespace-nowrap">
                  {row.method === 'fakt' ? (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-primary-bg px-2 py-1 text-xs font-medium text-primary">
                      <CreditCard className="h-3.5 w-3.5" />
                      Fakt
                    </div>
                  ) : (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-teal-bg px-2 py-1 text-xs font-medium text-teal">
                      <Banknote className="h-3.5 w-3.5" />
                      Naqt
                    </div>
                  )}
                </td>
                <td className="px-5 py-3 text-[var(--text-secondary)] whitespace-nowrap">
                  {row.created_by || 'Tizim'}
                </td>
                <td className="px-5 py-3 text-[var(--text-secondary)] max-w-[200px] truncate" title={row.notes}>
                  {row.notes || '-'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
