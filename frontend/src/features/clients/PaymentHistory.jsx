import { useState, useMemo } from 'react';
import { History, Banknote, CreditCard, CalendarDays, AlertTriangle, Download, Wallet, Receipt, Sparkles, Layers } from 'lucide-react';
import { usePayments, usePrepayments } from './hooks';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';
import { downloadFile } from '@/lib/download';
import { PaymentReceipt } from './PaymentReceipt';
import { cn } from '@/lib/utils';

export function PaymentHistory({ clientId, client }) {
  const { data: payments = [], isLoading: pLoading } = usePayments(clientId);
  const { data: prepayments = [], isLoading: prLoading } = usePrepayments(clientId);
  const [exporting, setExporting] = useState(false);
  const [receiptOpen, setReceiptOpen] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState(null);

  const isLoading = pLoading || prLoading;

  const history = useMemo(() => {
    // 1. Group debt payments by debt_id
    const debtGroups = {};
    const standalonePayments = [];

    payments.forEach((p) => {
      if (p.is_debt && p.debt_id) {
        if (!debtGroups[p.debt_id]) {
          debtGroups[p.debt_id] = {
            debt_id: p.debt_id,
            paid_at: p.paid_at,
            method: p.method,
            created_by: p.created_by,
            payments: [],
            total_debt_amount: 0,
          };
        }
        debtGroups[p.debt_id].payments.push(p);
        debtGroups[p.debt_id].total_debt_amount += parseFloat(p.applied_amount || p.amount || 0);
      } else {
        standalonePayments.push({ ...p, _type: 'payment' });
      }
    });

    // 2. Separate prepayments into debt overpayment vs standalone prepayment
    const standalonePrepayments = [];
    const usedPrepaymentIds = new Set();

    prepayments.forEach((pr) => {
      let attachedToDebt = false;
      const prTime = new Date(pr.paid_at).getTime();

      for (const debtId of Object.keys(debtGroups)) {
        const group = debtGroups[debtId];
        const groupTime = new Date(group.paid_at).getTime();
        // If within 60 seconds of debt payment or notes mentions qarz
        if (
          Math.abs(prTime - groupTime) < 60000 &&
          (pr.notes?.includes('qarz') || group.payments.some((p) => p.notes?.includes('ortiqcha')))
        ) {
          group.prepayment = pr;
          attachedToDebt = true;
          usedPrepaymentIds.add(pr.id);
          break;
        }
      }

      if (!attachedToDebt) {
        standalonePrepayments.push({ ...pr, _type: 'prepayment' });
      }
    });

    // 3. Transform debt groups into consolidated transaction rows
    const consolidatedDebtRows = Object.values(debtGroups).map((group) => {
      const debtAmount = group.total_debt_amount;
      const overpaymentAmount = group.prepayment ? parseFloat(group.prepayment.amount || 0) : 0;
      const totalAmount = debtAmount + overpaymentAmount;
      const periods = group.payments.map((p) => p.period).sort();

      const debtItems = group.payments.map((p) => ({
        type: 'debt',
        period: p.period,
        period_label: formatPeriod(p.period),
        amount: String(p.applied_amount || p.amount),
        label: "Qarz yopildi",
      }));

      return {
        _type: 'consolidated_debt',
        id: `debt-${group.debt_id}`,
        debt_id: group.debt_id,
        paid_at: group.paid_at,
        method: group.method,
        created_by: group.created_by,
        amount: String(totalAmount),
        debt_amount: String(debtAmount),
        balance_added: String(overpaymentAmount),
        periods: periods,
        periods_label: periods.map((p) => formatPeriod(p)).join(', '),
        is_debt: true,
        has_balance: overpaymentAmount > 0,
        debt_items: debtItems,
        notes: group.payments[0]?.notes || group.prepayment?.notes,
        raw_group: group,
      };
    });

    // 4. Combine all and sort by paid_at descending
    const combined = [
      ...consolidatedDebtRows,
      ...standalonePayments,
      ...standalonePrepayments,
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
    if (row._type === 'consolidated_debt') {
      const monthlyAmount = client?.monthly_amount ? Number(client.monthly_amount) : 0;
      const prepaidItems = [];
      const overpaymentNum = parseFloat(row.balance_added || 0);

      if (monthlyAmount > 0 && overpaymentNum > 0) {
        const estMonths = Math.floor(overpaymentNum / monthlyAmount);
        const lastDebtPeriod = row.periods[row.periods.length - 1];
        if (lastDebtPeriod) {
          const startDate = new Date(lastDebtPeriod + '-01');
          for (let i = 1; i <= estMonths; i++) {
            const nextDate = new Date(startDate.getFullYear(), startDate.getMonth() + i, 1);
            const periodStr = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}`;
            prepaidItems.push({
              type: 'prepaid',
              period: periodStr,
              period_label: formatPeriod(periodStr),
              amount: String(monthlyAmount),
              label: "Oldindan to'lov (balansga)",
            });
          }
        }
      }

      setSelectedPayment({
        receipt_id: `CHK-${new Date(row.paid_at).toISOString().slice(0, 10).replace(/-/g, '')}-${String(row.debt_id).padStart(6, '0')}`,
        paid_at: row.paid_at,
        payment_method: row.method,
        total_amount: row.amount,
        debt_amount_paid: row.debt_amount,
        prepaid_amount: row.balance_added,
        balance_added: row.balance_added,
        client: client,
        debt_items: row.debt_items,
        prepaid_items: prepaidItems,
        balance_item: parseFloat(row.balance_added) > 0 ? {
          amount: row.balance_added,
          label: "Balansga tushgan summa",
        } : null,
        summary: {
          debt_remaining: '0.00',
          new_balance: client?.balance,
          paid_up_to: prepaidItems.length > 0 ? prepaidItems[prepaidItems.length - 1].period : client?.last_paid_period,
          months_debt_closed: row.debt_items.length,
          months_prepaid: prepaidItems.length,
          created_by: row.created_by,
        },
      });
    } else {
      setSelectedPayment(row);
    }
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
              <th className="px-5 py-3 font-medium">Kiritilgan summa</th>
              <th className="px-5 py-3 font-medium">Turi</th>
              <th className="px-5 py-3 font-medium">Usul</th>
              <th className="px-5 py-3 font-medium">Kiritgan xodim</th>
              <th className="px-5 py-3 font-medium text-center">Chek</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-[var(--border)]">
            {history.map((row) => (
              <tr key={`${row._type}-${row.id}`} className="hover:bg-bg-light/50 transition-colors">
                <td className="px-5 py-3 text-[var(--text-primary)] whitespace-nowrap">
                  {formatDate(row.paid_at)}
                </td>

                <td className="px-5 py-3 whitespace-nowrap">
                  {row._type === 'consolidated_debt' ? (
                    <div>
                      <span className="font-semibold text-[var(--text-primary)]">
                        {row.periods_label}
                      </span>
                      <span className="block text-[11px] text-[var(--text-secondary)]">
                        {row.periods.length} oy qarz yopildi
                        {row.has_balance && ` + Balansga ${formatMoney(row.balance_added)} UZS`}
                      </span>
                    </div>
                  ) : row._type === 'payment' ? (
                    <div>
                      <span className="font-semibold text-[var(--text-primary)]">
                        {formatPeriod(row.period)}
                      </span>
                      <span className="block text-[11px] text-[var(--text-secondary)]">
                        Oylik abonent to'lovi
                      </span>
                    </div>
                  ) : (
                    <div>
                      <span className="font-semibold text-[var(--text-primary)]">
                        {row.notes || "Oldindan to'lov"}
                      </span>
                      <span className="block text-[11px] text-teal">
                        Balans to'ldirildi
                      </span>
                    </div>
                  )}
                </td>

                <td className="px-5 py-3 whitespace-nowrap">
                  <div className="flex flex-col">
                    <span className="text-sm font-bold text-[var(--text-primary)] font-mono">
                      {formatMoney(row.amount)} <span className="text-xs font-normal text-[var(--text-secondary)]">UZS</span>
                    </span>
                    {row._type === 'consolidated_debt' ? (
                      <span className="text-[11px] text-[var(--text-secondary)] mt-0.5">
                        Qarzdan: {formatMoney(row.debt_amount)}
                        {row.has_balance && ` • Balans: +${formatMoney(row.balance_added)}`}
                      </span>
                    ) : row._type === 'payment' ? (
                      <span className="text-[11px] text-success-text mt-0.5">
                        Yechildi: {formatMoney(row.applied_amount || row.amount)} UZS
                      </span>
                    ) : (
                      <span className="text-[11px] text-teal mt-0.5">
                        Balansga: +{formatMoney(row.amount)} UZS
                      </span>
                    )}
                  </div>
                </td>

                <td className="px-5 py-3 whitespace-nowrap">
                  {row._type === 'consolidated_debt' ? (
                    row.has_balance ? (
                      <div className="inline-flex items-center gap-1.5 rounded-md bg-teal-bg px-2 py-1 text-xs font-semibold text-teal">
                        <Sparkles className="h-3.5 w-3.5" />
                        Qarz + Avans
                      </div>
                    ) : (
                      <div className="inline-flex items-center gap-1.5 rounded-md bg-warning-bg px-2 py-1 text-xs font-semibold text-warning-text">
                        <AlertTriangle className="h-3.5 w-3.5" />
                        Qarz to'lovi
                      </div>
                    )
                  ) : row._type === 'prepayment' ? (
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
                      <CreditCard className="h-3.5 w-3.5 text-primary" />
                      Fakt
                    </div>
                  ) : (
                    <div className="inline-flex items-center gap-1.5 rounded-md bg-bg-light px-2 py-1 text-xs font-medium text-[var(--text-secondary)]">
                      <Banknote className="h-3.5 w-3.5 text-teal" />
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
                    className="inline-flex items-center gap-1 rounded-lg border border-[var(--border)] bg-[var(--card-bg)] px-2.5 py-1.5 text-xs font-medium text-[var(--text-secondary)] transition-all hover:border-primary hover:bg-primary-bg hover:text-primary shadow-sm"
                    title="Chekni ko'rish va chop etish"
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
