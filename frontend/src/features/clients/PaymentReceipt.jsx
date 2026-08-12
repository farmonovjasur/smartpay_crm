import { useRef } from 'react';
import { X, Printer, Receipt, CreditCard, Banknote } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { formatDate, formatPeriod } from '@/lib/date';
import { formatMoney } from '@/lib/money';

/**
 * To'lov cheki (receipt) modali.
 * Professional termal printer uslubidagi chek dizayni.
 * Brauzer print orqali chop etish imkoniyati bilan.
 */
export function PaymentReceipt({ open, onOpenChange, payment, client }) {
  const receiptRef = useRef(null);

  if (!payment || !client) return null;

  const isDebt = payment.is_debt;
  const isPrepayment = payment._type === 'prepayment';
  const typeLabel = isPrepayment ? "Oldindan to'lov" : isDebt ? "Qarz to'lovi" : "Oylik to'lov";
  const methodLabel = payment.method === 'fakt' ? 'Fakt (online)' : 'Naqt';
  const displayAmount = isPrepayment ? payment.amount : (payment.applied_amount || payment.amount);
  const receiptId = `CHK-${String(payment.id).padStart(6, '0')}`;
  
  const now = new Date();
  const printTimestamp = `${String(now.getDate()).padStart(2, '0')}.${String(now.getMonth() + 1).padStart(2, '0')}.${now.getFullYear()} ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;

  function handlePrint() {
    const content = receiptRef.current;
    if (!content) return;

    const printWindow = window.open('', '_blank', 'width=440,height=720');
    if (!printWindow) return;

    printWindow.document.write(`<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<title>To'lov cheki — ${receiptId}</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:#fff;color:#1e293b;-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{@page{size:80mm auto;margin:3mm}body{padding:0}.no-print{display:none!important}}
</style>
</head>
<body>${content.innerHTML}
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close()};setTimeout(function(){window.close()},8000)};<\/script>
</body>
</html>`);
    printWindow.document.close();
  }

  // Type colors
  const typeColors = isPrepayment
    ? { bg: '#d1fae5', text: '#065f46', border: '#a7f3d0' }
    : isDebt
    ? { bg: '#fef3c7', text: '#92400e', border: '#fde68a' }
    : { bg: '#eef2ff', text: '#4338ca', border: '#c7d2fe' };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[460px] p-0 overflow-hidden">
        {/* ── Modal Header ── */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '16px 20px',
          borderBottom: '1px solid var(--border)',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{
              width: 34, height: 34, borderRadius: 10,
              background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
            }}>
              <Receipt style={{ width: 16, height: 16, color: '#fff' }} />
            </div>
            <span style={{ fontSize: 16, fontWeight: 700, color: 'var(--text-primary)' }}>
              To'lov cheki
            </span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <button
              type="button"
              onClick={handlePrint}
              style={{
                display: 'inline-flex', alignItems: 'center', gap: 6,
                padding: '7px 14px', borderRadius: 8, border: 'none',
                background: 'linear-gradient(135deg, #6366f1, #7c3aed)',
                color: '#fff', fontSize: 12, fontWeight: 600, cursor: 'pointer',
                boxShadow: '0 2px 8px rgba(99,102,241,0.35)',
                transition: 'transform 0.15s, box-shadow 0.15s',
              }}
              onMouseOver={e => { e.currentTarget.style.transform = 'translateY(-1px)'; e.currentTarget.style.boxShadow = '0 4px 14px rgba(99,102,241,0.45)'; }}
              onMouseOut={e => { e.currentTarget.style.transform = 'translateY(0)'; e.currentTarget.style.boxShadow = '0 2px 8px rgba(99,102,241,0.35)'; }}
            >
              <Printer style={{ width: 14, height: 14 }} />
              Chop etish
            </button>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              style={{
                width: 32, height: 32, borderRadius: 8, border: '1px solid var(--border)',
                background: 'var(--card-bg)', display: 'flex', alignItems: 'center', justifyContent: 'center',
                cursor: 'pointer', color: 'var(--text-secondary)',
              }}
              aria-label="Yopish"
            >
              <X style={{ width: 16, height: 16 }} />
            </button>
          </div>
        </div>

        {/* ── Receipt Preview ── */}
        <div style={{ padding: '20px 24px', overflowY: 'auto', maxHeight: '72vh', background: '#f1f5f9' }}>
          <div
            ref={receiptRef}
            style={{
              maxWidth: 370, margin: '0 auto',
              background: '#ffffff',
              borderRadius: 16,
              boxShadow: '0 4px 24px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.04)',
              overflow: 'hidden',
              fontFamily: "'Inter', system-ui, sans-serif",
              color: '#1e293b',
            }}
          >
            {/* ─── Header gradient ─── */}
            <div style={{
              background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a78bfa 100%)',
              padding: '28px 24px 22px',
              textAlign: 'center',
              position: 'relative',
            }}>
              {/* Logo / Brand */}
              <div style={{
                width: 48, height: 48, borderRadius: 14,
                background: 'rgba(255,255,255,0.2)',
                backdropFilter: 'blur(8px)',
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                marginBottom: 10,
                border: '1px solid rgba(255,255,255,0.25)',
              }}>
                <span style={{ fontSize: 22, fontWeight: 800, color: '#fff' }}>S</span>
              </div>
              <div style={{ fontSize: 20, fontWeight: 800, color: '#ffffff', letterSpacing: 1 }}>
                SmartPay
              </div>
              <div style={{
                fontSize: 11, fontWeight: 600, color: 'rgba(255,255,255,0.8)',
                marginTop: 4, textTransform: 'uppercase', letterSpacing: 2,
              }}>
                To'lov cheki
              </div>
              <div style={{
                marginTop: 10,
                display: 'inline-block',
                padding: '4px 14px',
                borderRadius: 20,
                background: 'rgba(255,255,255,0.2)',
                fontSize: 11, fontWeight: 600, color: '#fff',
                fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
                letterSpacing: 0.8,
                border: '1px solid rgba(255,255,255,0.15)',
              }}>
                {receiptId}
              </div>
            </div>

            {/* ─── Dashed separator ─── */}
            <div style={{
              borderBottom: '2px dashed #e2e8f0',
              margin: '0 20px',
            }} />

            {/* ─── Client section ─── */}
            <div style={{ padding: '18px 24px 14px' }}>
              <div style={{
                fontSize: 9, fontWeight: 700, color: '#94a3b8',
                textTransform: 'uppercase', letterSpacing: 1.5,
                marginBottom: 12,
              }}>
                Mijoz ma'lumotlari
              </div>

              <ReceiptRow label="Ism" value={client.name} />
              <ReceiptRow label="INN" value={client.inn} mono />
              {client.phone && <ReceiptRow label="Telefon" value={client.phone} mono />}
            </div>

            {/* ─── Dashed separator ─── */}
            <div style={{ borderBottom: '2px dashed #e2e8f0', margin: '0 20px' }} />

            {/* ─── Payment details ─── */}
            <div style={{ padding: '18px 24px 14px' }}>
              <div style={{
                fontSize: 9, fontWeight: 700, color: '#94a3b8',
                textTransform: 'uppercase', letterSpacing: 1.5,
                marginBottom: 12,
              }}>
                To'lov tafsilotlari
              </div>

              {/* Type badge */}
              <div style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                marginBottom: 10,
              }}>
                <span style={{ fontSize: 12, color: '#64748b', fontWeight: 500 }}>Turi</span>
                <span style={{
                  display: 'inline-flex', alignItems: 'center', gap: 5,
                  padding: '4px 12px', borderRadius: 8,
                  background: typeColors.bg, color: typeColors.text,
                  border: `1px solid ${typeColors.border}`,
                  fontSize: 11, fontWeight: 700,
                }}>
                  {typeLabel}
                </span>
              </div>

              {!isPrepayment && payment.period && (
                <ReceiptRow label="Davr" value={formatPeriod(payment.period)} bold />
              )}
              {isPrepayment && payment.notes && (
                <ReceiptRow label="Izoh" value={payment.notes} />
              )}
              <ReceiptRow
                label="To'lov usuli"
                value={
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                    {payment.method === 'fakt'
                      ? <CreditCard style={{ width: 13, height: 13, color: '#6366f1' }} />
                      : <Banknote style={{ width: 13, height: 13, color: '#0d9488' }} />
                    }
                    <span>{methodLabel}</span>
                  </span>
                }
              />
              <ReceiptRow label="Sana" value={formatDate(payment.paid_at)} />
              {payment.created_by && (
                <ReceiptRow label="Xodim" value={payment.created_by} />
              )}
            </div>

            {/* ─── Dashed separator ─── */}
            <div style={{ borderBottom: '2px dashed #e2e8f0', margin: '0 20px' }} />

            {/* ─── Amount section ─── */}
            <div style={{ padding: '18px 24px 16px' }}>
              <div style={{
                fontSize: 9, fontWeight: 700, color: '#94a3b8',
                textTransform: 'uppercase', letterSpacing: 1.5,
                marginBottom: 12,
              }}>
                Summa
              </div>

              {!isPrepayment && payment.applied_amount && payment.applied_amount !== payment.amount && (
                <div style={{
                  display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                  marginBottom: 8,
                }}>
                  <span style={{ fontSize: 12, color: '#94a3b8', fontWeight: 500 }}>Kiritildi</span>
                  <span style={{ fontSize: 12, color: '#94a3b8', fontWeight: 500 }}>
                    {formatMoney(payment.amount)} UZS
                  </span>
                </div>
              )}

              {/* Main amount — hero */}
              <div style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                padding: '14px 16px',
                borderRadius: 12,
                background: 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)',
                border: '1px solid #bbf7d0',
              }}>
                <span style={{ fontSize: 13, fontWeight: 700, color: '#166534' }}>
                  {isPrepayment ? 'Balansga' : 'Yechildi'}
                </span>
                <span style={{ fontSize: 20, fontWeight: 800, color: '#15803d', letterSpacing: -0.5 }}>
                  {formatMoney(displayAmount)}
                  <span style={{ fontSize: 12, fontWeight: 600, marginLeft: 4, color: '#22c55e' }}>UZS</span>
                </span>
              </div>
            </div>

            {/* ─── Decorative dots ─── */}
            <div style={{
              textAlign: 'center', padding: '2px 0 6px',
              color: '#cbd5e1', fontSize: 14, letterSpacing: 6,
              lineHeight: 1,
            }}>
              {'•'.repeat(18)}
            </div>

            {/* ─── Footer ─── */}
            <div style={{
              padding: '14px 24px 24px',
              textAlign: 'center',
            }}>
              <div style={{ fontSize: 13, fontWeight: 700, color: '#334155' }}>
                Xizmatimizdan foydalanganingiz
              </div>
              <div style={{ fontSize: 13, fontWeight: 700, color: '#334155', marginBottom: 8 }}>
                uchun rahmat!
              </div>
              <div style={{
                fontSize: 10, color: '#94a3b8',
                fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
                marginBottom: 4,
              }}>
                Chop etilgan: {printTimestamp}
              </div>
              <div style={{
                fontSize: 10, color: '#94a3b8', lineHeight: 1.5,
                maxWidth: 260, margin: '0 auto',
              }}>
                SmartPay CRM tizimi orqali avtomatik yaratilgan hujjat
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
      padding: '5px 0',
      gap: 12,
    }}>
      <span style={{ fontSize: 12, color: '#64748b', fontWeight: 500, flexShrink: 0 }}>
        {label}
      </span>
      <span style={{
        fontSize: 12,
        fontWeight: bold ? 700 : 600,
        color: '#1e293b',
        textAlign: 'right',
        fontFamily: mono ? "'JetBrains Mono', 'Fira Code', monospace" : 'inherit',
        wordBreak: 'break-word',
      }}>
        {value}
      </span>
    </div>
  );
}
