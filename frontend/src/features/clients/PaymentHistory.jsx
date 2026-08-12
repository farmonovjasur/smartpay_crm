import { useState, useMemo } from 'react';
import { History, Banknote, CreditCard, CalendarDays, AlertTriangle, Download, Wallet, Receipt } from 'lucide-react';
import { usePayments, usePrepayments } from './hooks';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';
import { downloadFile } from '@/lib/download';
import { PaymentReceipt } from './PaymentReceipt';

export function PaymentHistory({ clientId, client }) {
  const { data: payments = [], isLoading: pLoading } = usePayments(clientId);
  const { data: prepayments = [], isLoading: prLoading } = usePrepayments(clientId);
  const [exporting, setExporting] = useState(false);
  const [receiptOpen, setReceiptOpen] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState(null);

  const isLoading = pLoading || prLoading;

  const history = useMemo(() => {
    const combined = [
      ...payments.map(p => ({ ...p, _type: 'payment' })),
      ...prepayments.map(p => ({ ...p, _type: 'prepayment' }))
    ];
    return combined.sort((a, b) => new Date(b.paid_at) - new Date(a.paid_at));
  }, [payments, prepayments]);

  async function handleExport() {
    setExporting(true);
    try {
      await downloadFile(`/clients/${clientId}/payments/export`, undefined, 'tolovlar_tarixi.xlsx');
    } finally {
      setExporting(false);
    }
  }

  function handleOpenReceipt(row) {
    setSelectedPayment(row);
    setReceiptOpen(true);
  }

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
      <div className="flex items-center justify-between border-b border-[var(--border)] px-5 py-4">
        <h3 className="text-base font-semibold text-[var(--text-primary)]">
          Umumiy to'lovlar tarixi
        </h3>
        {history.length > 0 && (
          <button
            type="button"
            onClick={handleExport}
            disabled={exporting}
            className="inline-flex items-center gap-1.5 rounded-lg border border-[var(--border)] bg-bg-light px-3 py-1.5 text-xs font-medium text-[var(--text-secondary)] transition-colors hover:bg-[var(--hover-bg)] hover:text-[var(--text-primary)] disabled:opacity-50"
          >
            <Download className="h-3.5 w-3.5" />
            {exporting ? 'Yuklanmoqda...' : 'Excel yuklash'}
          </button>
        )}
      </div>
      
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="bg-bg-light text-[var(--text-secondary)]">
            <tr>
              <th className="px-5 py-3 font-medium">Sana</th>
              <th className="px-5 py-3 font-medium">Davr / Izoh</th>
              <th className="px-5 py-3 font-medium">Summa</th>
              <th className="px-5 py-3 font-medium">Turi</th>
              <th className="px-5 py-3 font-medium">Usul</th>
              <th className="px-5 py-3 font-medium">Kiritgan xodim</th>
              <th className="px-5 py-3 font-medium text-center">Chek</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {history.map((row) => (
              <tr key={`${row._type}-${row.id}`} className="hover:bg-bg-light/50">
                <td className="px-5 py-3 text-[var(--text-primary)] whitespace-nowrap">
                  {formatDate(row.paid_at)}
                </td>
                <td className="px-5 py-3 font-medium text-[var(--text-primary)] whitespace-nowrap">
                  {row._type === 'payment' ? formatPeriod(row.period) : (row.notes || '-')}
                </td>
                <td className="px-5 py-3 whitespace-nowrap">
                  <div className="flex flex-col">
                    <span className="text-[var(--text-secondary)] text-xs font-normal">
                      Kiritildi: {formatMoney(row.amount)} UZS
                    </span>
                    <span className="text-success-text font-semibold mt-0.5">
                      {row._type === 'prepayment' ? 'Balansga: ' : 'Yechildi: '}
                      {formatMoney(row._type === 'prepayment' ? row.amount : (row.applied_amount || row.amount))} UZS
                    </span>
                  </div>
                </td>
                <td className="px-5 py-3 whitespace-nowrap">
                  {row._type === 'prepayment' ? (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-teal-bg px-2 py-1 text-xs font-medium text-teal">
                      <Wallet className="h-3.5 w-3.5" />
                      Oldindan to'lov
                    </div>
                  ) : row.is_debt ? (
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
                <td className="px-5 py-3 text-center whitespace-nowrap">
                  <button
                    type="button"
                    onClick={() => handleOpenReceipt(row)}
                    className="inline-flex items-center gap-1 rounded-lg border border-[var(--border)] bg-[var(--card-bg)] px-2.5 py-1.5 text-xs font-medium text-[var(--text-secondary)] transition-all hover:border-primary hover:bg-primary-bg hover:text-primary"
                    title="Chekni ko'rish"
                  >
                    <Receipt className="h-3.5 w-3.5" />
                    Chek
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Receipt Modal */}
      <PaymentReceipt
        open={receiptOpen}
        onOpenChange={setReceiptOpen}
        payment={selectedPayment}
        client={client}
      />
    </div>
  );
}
