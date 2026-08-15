import { useRef } from 'react';
import { X, Printer, Receipt, CreditCard, Banknote, CheckCircle2, Calendar, AlertTriangle, ShieldCheck } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';

/**
 * To'lov cheki (receipt) modali.
 * Qarz yopilishi va kelgusi oylar oldindan to'lov taqsimotini (FIFO) to'liq qo'llab-quvvatlaydi.
 * Professional termal printer (80mm) va standart brauzer chop etish imkoniyati bilan.
 */
export function PaymentReceipt({ open, onOpenChange, payment, client: directClient }) {
  const receiptRef = useRef(null);

  if (!payment) return null;

  // Agar payment ichida client obyekti kelsa, undan foydalanamiz
  const client = payment.client || directClient;
  if (!client) return null;

  const isStructuredReceipt = Boolean(payment.debt_items || payment.prepaid_items || payment.receipt_id || payment._type === 'consolidated_debt');
  const debtItems = payment.debt_items || [];
  const prepaidItems = payment.prepaid_items || [];
  const balanceItem = payment.balance_item || null;
  const summary = payment.summary || {};

  // 1. Oylik to'lov summasini aniqlash (standart 100 000 UZS yoki product_count * 100000)
  const monthlyAmount = client?.monthly_amount
    ? Number(client.monthly_amount)
    : (Number(client?.product_count || 1) * 100000);

  // 2. Ortiqcha / oldindan to'lov summasi
  const effectiveOverpayment = Number(
    payment.prepaid_amount ||
    payment.balance_added ||
    balanceItem?.amount ||
    (payment._type === 'prepayment' ? payment.amount : 0)
  );

  // 3. Agar prepaidItems bo'sh bo'lsa, ortiqcha summadan kelgusi oylarni avtomatik tuzish
  let effectivePrepaidItems = [...prepaidItems];
  if (effectivePrepaidItems.length === 0 && effectiveOverpayment > 0 && monthlyAmount > 0) {
    const estMonths = Math.floor(effectiveOverpayment / monthlyAmount);
    let lastPeriodStr = debtItems.length > 0 ? debtItems[debtItems.length - 1].period : client.last_paid_period;
    let baseDate;
    if (lastPeriodStr && /^\d{4}-\d{2}$/.test(lastPeriodStr)) {
      const [y, m] = lastPeriodStr.split('-').map(Number);
      baseDate = new Date(y, m - 1, 1);
    } else {
      const paidDate = payment.paid_at ? new Date(payment.paid_at) : new Date();
      baseDate = new Date(paidDate.getFullYear(), paidDate.getMonth(), 1);
    }

    for (let i = 1; i <= estMonths; i++) {
      const nextDate = new Date(baseDate.getFullYear(), baseDate.getMonth() + i, 1);
      const periodStr = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}`;
      effectivePrepaidItems.push({
        type: 'prepaid',
        period: periodStr,
        period_label: formatPeriod(periodStr),
        amount: String(monthlyAmount),
        label: "Oldindan to'lov",
      });
    }
  }

  const totalAmount = isStructuredReceipt
    ? (payment.total_amount || payment.amount || 0)
    : (payment._type === 'prepayment' ? payment.amount : (payment.applied_amount || payment.amount));

  const finalDebtTotal = payment.debt_amount_paid || (debtItems.length > 0 ? debtItems.reduce((acc, i) => acc + Number(i.amount || 0), 0) : null);
  const finalPrepaidTotal = effectiveOverpayment > 0 ? effectiveOverpayment : (effectivePrepaidItems.length > 0 ? effectivePrepaidItems.reduce((acc, i) => acc + Number(i.amount || 0), 0) : null);

  const isDebt = payment.is_debt || debtItems.length > 0;
  const isPrepayment = payment._type === 'prepayment' || (effectivePrepaidItems.length > 0 && debtItems.length === 0);
  const isCombined = debtItems.length > 0 && effectivePrepaidItems.length > 0;

  const typeLabel = isCombined
    ? "Qarz + Oldindan to'lov"
    : isPrepayment
    ? "Oldindan to'lov"
    : isDebt
    ? "Qarz to'lovi"
    : "Oylik to'lov";

  const method = payment.payment_method || payment.method || 'naqt';
  const methodLabel = method === 'fakt' ? 'Fakt (online)' : 'Naqt';
  const receiptId = payment.receipt_id || `CHK-${String(payment.id || Date.now()).padStart(6, '0')}`;
  const paidAtDate = payment.paid_at ? new Date(payment.paid_at) : new Date();

  const now = new Date();
  const printTimestamp = `${String(now.getDate()).padStart(2, '0')}.${String(now.getMonth() + 1).padStart(2, '0')}.${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

  function handlePrint() {
    const content = receiptRef.current;
    if (!content) return;

    const printWindow = window.open('', '_blank', 'width=440,height=750');
    if (!printWindow) return;

    printWindow.document.write(`<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>To'lov cheki — ${receiptId}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:#fff;color:#0f172a;-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{
  @page{size:80mm auto;margin:2mm}
  body{padding:0}
  .no-print{display:none!important}
}
</style>
</head>
<body>${content.innerHTML}
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close()};setTimeout(function(){window.close()},8000)};<\/script>
</body>
</html>`);
    printWindow.document.close();
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[480px] p-0 overflow-hidden">
        {/* ── Modal Header ── */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '16px 20px',
          borderBottom: '1px solid var(--border)',
          background: 'var(--card-bg)',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{
              width: 36, height: 36, borderRadius: 10,
              background: 'linear-gradient(135deg, #4f46e5, #7c3aed)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 2px 8px rgba(99, 102, 241, 0.3)',
            }}>
              <Receipt style={{ width: 18, height: 18, color: '#fff' }} />
            </div>
            <div>
              <span style={{ fontSize: 16, fontWeight: 700, color: 'var(--text-primary)', display: 'block' }}>
                To'lov cheki
              </span>
              <span style={{ fontSize: 11, color: 'var(--text-secondary)' }}>
                {receiptId}
              </span>
            </div>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <button
              type="button"
              onClick={handlePrint}
              style={{
                display: 'inline-flex', alignItems: 'center', gap: 6,
                padding: '7px 14px', borderRadius: 8, border: 'none',
                background: 'linear-gradient(135deg, #4f46e5, #7c3aed)',
                color: '#fff', fontSize: 12, fontWeight: 600, cursor: 'pointer',
                boxShadow: '0 2px 8px rgba(99,102,241,0.35)',
                transition: 'all 0.15s ease',
              }}
            >
              <Printer style={{ width: 14, height: 14 }} />
              Chop etish
            </button>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              style={{
                width: 32, height: 32, borderRadius: 8,
                border: '1px solid var(--border)',
                background: 'var(--card-bg)',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: 'pointer', color: 'var(--text-secondary)',
              }}
              aria-label="Yopish"
            >
              <X style={{ width: 16, height: 16 }} />
            </button>
          </div>
        </div>

        {/* ── Scrollable Receipt Area ── */}
        <div style={{
          padding: '20px',
          overflowY: 'auto',
          maxHeight: '74vh',
          background: '#f1f5f9',
        }}>
          {/* Printable Receipt Paper (80mm width standard) */}
          <div
            ref={receiptRef}
            style={{
              maxWidth: '390px',
              margin: '0 auto',
              background: '#ffffff',
              borderRadius: '16px',
              boxShadow: '0 4px 24px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.03)',
              overflow: 'hidden',
              fontFamily: '"Inter", system-ui, sans-serif',
              color: '#0f172a',
            }}
          >
            {/* ─── Header Branding ─── */}
            <div style={{
              background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
              color: '#ffffff',
              padding: '22px 20px 18px',
              textAlign: 'center',
            }}>
              <div style={{
                display: 'inline-flex', alignItems: 'center', gap: 6,
                background: 'rgba(255,255,255,0.18)',
                padding: '4px 12px', borderRadius: 20,
                fontSize: 11, fontWeight: 700, letterSpacing: 0.5,
                marginBottom: 8, backdropFilter: 'blur(4px)',
              }}>
                <ShieldCheck style={{ width: 14, height: 14 }} />
                SmartPay CRM
              </div>
              <h2 style={{
                fontSize: 18, fontWeight: 800, letterSpacing: 0.5,
                margin: '2px 0 4px', textTransform: 'uppercase',
              }}>
                TO'LOV KVITANSIYASI
              </h2>
              <p style={{
                fontSize: 11, opacity: 0.9,
                fontFamily: "'JetBrains Mono', monospace",
                letterSpacing: 0.5,
              }}>
                {receiptId}
              </p>
            </div>

            {/* ─── Client Section ─── */}
            <div style={{ padding: '14px 20px 10px' }}>
              <div style={{
                fontSize: 10, fontWeight: 700, color: '#64748b',
                textTransform: 'uppercase', letterSpacing: 1.2,
                marginBottom: 8,
              }}>
                Mijoz ma'lumotlari
              </div>
              <ReceiptRow label="Mijoz" value={client.name} bold />
              <ReceiptRow label="INN" value={client.inn} mono />
              {client.phone && <ReceiptRow label="Telefon" value={client.phone} mono />}
            </div>

            {/* ─── Dashed separator ─── */}
            <div style={{ borderBottom: '2px dashed #e2e8f0', margin: '0 16px' }} />

            {/* ─── Payment Details ─── */}
            <div style={{ padding: '14px 20px 10px' }}>
              <div style={{
                fontSize: 10, fontWeight: 700, color: '#64748b',
                textTransform: 'uppercase', letterSpacing: 1.2,
                marginBottom: 8,
              }}>
                To'lov tafsilotlari
              </div>

              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '4px 0' }}>
                <span style={{ fontSize: 12, color: '#64748b', fontWeight: 500 }}>To'lov turi</span>
                <span style={{
                  fontSize: 11, fontWeight: 700,
                  padding: '3px 10px', borderRadius: 6,
                  background: isCombined ? '#fdf2f8' : isPrepayment ? '#ecfdf5' : '#fef3c7',
                  color: isCombined ? '#9d174d' : isPrepayment ? '#065f46' : '#92400e',
                  border: `1px solid ${isCombined ? '#fbcfe8' : isPrepayment ? '#a7f3d0' : '#fde68a'}`,
                }}>
                  {typeLabel}
                </span>
              </div>

              <ReceiptRow
                label="To'lov usuli"
                value={
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                    {method === 'fakt'
                      ? <CreditCard style={{ width: 13, height: 13, color: '#4f46e5' }} />
                      : <Banknote style={{ width: 13, height: 13, color: '#0d9488' }} />
                    }
                    <span>{methodLabel}</span>
                  </span>
                }
              />
              <ReceiptRow label="To'lov sanasi" value={formatDate(paidAtDate)} mono />
              {(payment.created_by || summary.created_by) && (
                <ReceiptRow label="Xodim" value={payment.created_by || summary.created_by} />
              )}
            </div>

            {/* ─── Dashed separator ─── */}
            <div style={{ borderBottom: '2px dashed #e2e8f0', margin: '0 16px' }} />

            {/* ─── Breakdown Section (Qarz va Oldindan to'lov taqsimoti) ─── */}
            {isStructuredReceipt && (debtItems.length > 0 || effectivePrepaidItems.length > 0) ? (
              <div style={{ padding: '14px 20px' }}>
                <div style={{
                  fontSize: 10, fontWeight: 700, color: '#64748b',
                  textTransform: 'uppercase', letterSpacing: 1.2,
                  marginBottom: 10,
                }}>
                  To'lov taqsimoti:
                </div>

                {/* 🔴 1. [QARZ YOPILDI] */}
                {debtItems.length > 0 && (
                  <div style={{
                    marginBottom: 12,
                    background: '#fef2f2',
                    border: '1.5px solid #fecaca',
                    borderRadius: 10,
                    padding: '10px 12px',
                  }}>
                    <div style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      marginBottom: 6, paddingBottom: 4,
                      borderBottom: '1px dashed #fca5a5',
                    }}>
                      <span style={{ fontSize: 11, fontWeight: 800, color: '#991b1b', textTransform: 'uppercase', letterSpacing: 0.5 }}>
                        [QARZ YOPILDI]
                      </span>
                      {finalDebtTotal && (
                        <span style={{ fontSize: 12, fontWeight: 800, color: '#991b1b' }}>
                          {formatMoney(finalDebtTotal)} UZS
                        </span>
                      )}
                    </div>
                    {debtItems.map((item, idx) => (
                      <div key={idx} style={{
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                        fontSize: 12, padding: '3px 0', color: '#7f1d1d',
                      }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
                          <span style={{ width: 4, height: 4, borderRadius: '50%', background: '#ef4444' }} />
                          <span style={{ fontWeight: 500 }}>{item.period_label || formatPeriod(item.period)}:</span>
                        </span>
                        <span style={{ fontWeight: 700, fontFamily: "'JetBrains Mono', monospace" }}>
                          {formatMoney(item.amount)} UZS
                        </span>
                      </div>
                    ))}
                  </div>
                )}

                {/* 🟢 2. [OLDINDAN TO'LOV] */}
                {effectivePrepaidItems.length > 0 && (
                  <div style={{
                    marginBottom: 12,
                    background: '#f0fdf4',
                    border: '1.5px solid #bbf7d0',
                    borderRadius: 10,
                    padding: '10px 12px',
                  }}>
                    <div style={{
                      display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      marginBottom: 6, paddingBottom: 4,
                      borderBottom: '1px dashed #86efac',
                    }}>
                      <span style={{ fontSize: 11, fontWeight: 800, color: '#166534', textTransform: 'uppercase', letterSpacing: 0.5 }}>
                        [OLDINDAN TO'LOV]
                      </span>
                      {finalPrepaidTotal && (
                        <span style={{ fontSize: 12, fontWeight: 800, color: '#166534' }}>
                          {formatMoney(finalPrepaidTotal)} UZS
                        </span>
                      )}
                    </div>
                    {effectivePrepaidItems.map((item, idx) => (
                      <div key={idx} style={{
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                        fontSize: 12, padding: '3px 0', color: '#14532d',
                      }}>
                        <span style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
                          <span style={{ width: 4, height: 4, borderRadius: '50%', background: '#22c55e' }} />
                          <span style={{ fontWeight: 500 }}>{item.period_label || formatPeriod(item.period)}:</span>
                        </span>
                        <span style={{ fontWeight: 700, fontFamily: "'JetBrains Mono', monospace" }}>
                          {formatMoney(item.amount)} UZS
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              /* Single period fallback */
              <div style={{ padding: '14px 20px 10px' }}>
                {!isPrepayment && payment.period && (
                  <ReceiptRow label="To'langan davr" value={formatPeriod(payment.period)} bold />
                )}
                {payment.notes && (
                  <ReceiptRow label="Izoh" value={payment.notes} />
                )}
              </div>
            )}

            {/* ─── Dashed separator ─── */}
            <div style={{ borderBottom: '2px dashed #e2e8f0', margin: '0 16px' }} />

            {/* ─── Total Amount Card ─── */}
            <div style={{ padding: '16px 20px' }}>
              <div style={{
                background: 'linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)',
                border: '1.5px solid #cbd5e1',
                borderRadius: 12,
                padding: '14px 16px',
                textAlign: 'center',
              }}>
                <div style={{ fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: 1 }}>
                  Jami to'langan summa
                </div>
                <div style={{ fontSize: 24, fontWeight: 800, color: '#0f172a', margin: '4px 0', letterSpacing: -0.5 }}>
                  {formatMoney(totalAmount)}
                  <span style={{ fontSize: 14, fontWeight: 700, marginLeft: 6, color: '#4f46e5' }}>UZS</span>
                </div>
              </div>

              {/* Status Summary Pills */}
              <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 6 }}>
                {summary.debt_remaining !== undefined && (
                  <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    fontSize: 12, padding: '4px 8px', borderRadius: 6, background: '#f8fafc',
                  }}>
                    <span style={{ color: '#64748b' }}>Qoldiq qarz:</span>
                    <span style={{
                      fontWeight: 700,
                      color: parseFloat(summary.debt_remaining) > 0 ? '#dc2626' : '#16a34a',
                    }}>
                      {parseFloat(summary.debt_remaining) > 0
                        ? `${formatMoney(summary.debt_remaining)} UZS`
                        : '0 UZS (To\'liq yopildi)'
                      }
                    </span>
                  </div>
                )}

                {client.balance !== undefined && client.balance !== null && (
                  <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    fontSize: 12, padding: '4px 8px', borderRadius: 6, background: '#f0fdf4',
                  }}>
                    <span style={{ color: '#166534', fontWeight: 500 }}>Mijoz balansi:</span>
                    <span style={{ fontWeight: 700, color: '#16a34a' }}>
                      {formatMoney(client.balance)} UZS
                    </span>
                  </div>
                )}

                {(summary.paid_up_to || client.last_paid_period) && (
                  <div style={{
                    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                    fontSize: 12, padding: '4px 8px', borderRadius: 6, background: '#f8fafc',
                  }}>
                    <span style={{ color: '#64748b' }}>To'langan davr:</span>
                    <span style={{ fontWeight: 700, color: '#4f46e5' }}>
                      {summary.paid_up_to_label || formatPeriod(summary.paid_up_to || client.last_paid_period)} gacha
                    </span>
                  </div>
                )}
              </div>
            </div>

            {/* ─── Decorative Dots ─── */}
            <div style={{
              textAlign: 'center', padding: '0',
              color: '#cbd5e1', fontSize: 13, letterSpacing: 5,
              lineHeight: 1,
            }}>
              {'•'.repeat(22)}
            </div>

            {/* ─── Footer ─── */}
            <div style={{
              padding: '14px 20px 22px',
              textAlign: 'center',
            }}>
              <div style={{ fontSize: 12, fontWeight: 700, color: '#334155', marginBottom: 2 }}>
                To'lovingiz uchun rahmat!
              </div>
              <div style={{
                fontSize: 10, color: '#94a3b8',
                fontFamily: "'JetBrains Mono', monospace",
                marginBottom: 6,
              }}>
                Chop etilgan: {printTimestamp}
              </div>
              <div style={{
                fontSize: 9, color: '#94a3b8', lineHeight: 1.4,
                maxWidth: 280, margin: '0 auto',
              }}>
                SmartPay CRM tizimi orqali avtomatik shakllantirilgan rasmiy to'lov kvitansiyasi
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

/* ── Row helper component ── */
function ReceiptRow({ label, value, mono = false, bold = false }) {
  return (
    <div style={{
      display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start',
      padding: '4px 0',
      gap: 12,
    }}>
      <span style={{ fontSize: 12, color: '#64748b', fontWeight: 500, flexShrink: 0 }}>
        {label}
      </span>
      <span style={{
        fontSize: 12,
        fontWeight: bold ? 700 : 600,
        color: '#0f172a',
        textAlign: 'right',
        fontFamily: mono ? "'JetBrains Mono', monospace" : 'inherit',
        wordBreak: 'break-word',
      }}>
        {value}
      </span>
    </div>
  );
}

