import { useRef } from 'react';
import { X, Printer, ShieldCheck } from 'lucide-react';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { formatMoney } from '@/lib/money';
import { formatDate } from '@/lib/date';

export function LedgerReceipt({ open, onOpenChange, entry, payment }) {
  const receiptRef = useRef(null);

  if (!entry) return null;
  const receiptId = `CHK-${payment ? payment.id : String(entry.id || Date.now()).padStart(6, '0')}`;

  function handlePrint() {
    const content = receiptRef.current;
    if (!content) return;

    const printWindow = window.open('', '_blank', 'width=440,height=750');
    if (!printWindow) return;

    printWindow.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>Chek</title>
          <style>
            @page { margin: 0; size: 80mm auto; }
            body { font-family: 'Inter', sans-serif; margin: 0; padding: 12px; font-size: 12px; color: #000; width: 80mm; box-sizing: border-box; }
            * { box-sizing: border-box; }
            .hide-on-print { display: none !important; }
          </style>
        </head>
        <body>
          ${content.outerHTML}
          <script>
            setTimeout(() => { window.print(); window.close(); }, 300);
          </script>
        </body>
      </html>
    `);
    printWindow.document.close();
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[420px] gap-0 p-0 overflow-hidden bg-bg-light">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-[var(--border)] bg-[var(--card-bg)] px-5 py-3">
          <div className="flex items-center gap-2">
            <h2 className="text-sm font-semibold text-[var(--text-primary)]">To'lov cheki</h2>
          </div>
          <div className="flex items-center gap-2">
            <button onClick={handlePrint} className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-primary-hover">
              <Printer className="h-3.5 w-3.5" />
              Chop etish
            </button>
            <button onClick={() => onOpenChange(false)} className="rounded-md p-1.5 text-[var(--text-secondary)] hover:bg-[var(--hover-bg)]">
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Scrollable Receipt Area */}
        <div className="max-h-[75vh] overflow-y-auto bg-slate-100 p-6 flex justify-center">
          <div
            ref={receiptRef}
            style={{ width: '80mm', minHeight: '100px', background: '#fff', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)', fontFamily: 'system-ui, -apple-system, sans-serif' }}
          >
            {/* Brand Header */}
            <div style={{ background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)', color: '#ffffff', padding: '22px 20px 18px', textAlign: 'center' }}>
              <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, background: 'rgba(255,255,255,0.18)', padding: '4px 12px', borderRadius: 20, fontSize: 11, fontWeight: 700, letterSpacing: 0.5, marginBottom: 8, backdropFilter: 'blur(4px)' }}>
                <ShieldCheck style={{ width: 14, height: 14 }} />
                SmartPay CRM
              </div>
              <h2 style={{ fontSize: 18, fontWeight: 800, margin: '2px 0 4px', textTransform: 'uppercase' }}>To'lov Cheki</h2>
              <div style={{ fontSize: 13, opacity: 0.9 }}>Chek №: {receiptId}</div>
            </div>

            <div style={{ padding: '20px' }}>
              <div style={{ marginBottom: 16, padding: '12px 14px', background: '#f8fafc', borderRadius: 10, border: '1px dashed #cbd5e1' }}>
                <p style={{ fontSize: 11, color: '#64748b', fontWeight: 600, textTransform: 'uppercase', marginBottom: 2 }}>Mijoz</p>
                <p style={{ fontSize: 14, fontWeight: 700, color: '#0f172a', marginBottom: 2 }}>{entry.client_name}</p>
                <p style={{ fontSize: 12, color: '#475569', display: 'flex', justifyContent: 'space-between' }}>
                  <span>INN: {entry.client_inn}</span>
                </p>
              </div>

              {/* Items Table */}
              <div style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', borderBottom: '2px solid #e2e8f0', paddingBottom: 6, marginBottom: 8, fontSize: 11, fontWeight: 700, color: '#64748b', textTransform: 'uppercase' }}>
                  <span>Xizmat turi</span>
                  <span>Summa</span>
                </div>
                {(entry.items || []).map((item, idx) => (
                  <div key={idx} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid #f1f5f9', fontSize: 13 }}>
                    <div style={{ maxWidth: '65%', fontWeight: 500, color: '#334155' }}>
                      {item.description}
                      <div style={{ fontSize: 10, color: '#94a3b8', marginTop: 2 }}>
                        Sana: {formatDate(item.created_at)}
                      </div>
                    </div>
                    <div style={{ fontWeight: 600, color: '#0f172a', whiteSpace: 'nowrap' }}>{formatMoney(item.amount)}</div>
                  </div>
                ))}
              </div>

              {/* Totals */}
              <div style={{ borderTop: '2px solid #e2e8f0', paddingTop: 12, marginBottom: 16 }}>
                {payment ? (
                  <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 14, fontWeight: 700, color: '#16a34a' }}>
                      <span>Ushbu chek summasi:</span>
                      <span>{formatMoney(payment.amount)} so'm</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 13, fontWeight: 600, color: '#475569' }}>
                      <span>To'lov sanasi:</span>
                      <span style={{ color: '#0f172a' }}>{formatDate(payment.created_at)}</span>
                    </div>
                    {payment.created_by && (
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 11, fontWeight: 500, color: '#64748b' }}>
                        <span>Qabul qildi:</span>
                        <span>{payment.created_by}</span>
                      </div>
                    )}
                    <div style={{ borderTop: '1px dashed #cbd5e1', marginTop: 8, paddingTop: 8, display: 'flex', justifyContent: 'space-between', fontSize: 12, fontWeight: 600, color: '#475569' }}>
                      <span>Jami qarz:</span>
                      <span style={{ color: '#0f172a' }}>{formatMoney(entry.total_amount)} so'm</span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: 12, fontWeight: 600, color: '#475569' }}>
                      <span>Umumiy to'langan:</span>
                      <span style={{ color: '#0f172a' }}>{formatMoney(entry.paid_amount)} so'm</span>
                    </div>
                    {entry.remaining_amount > 0 && (
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontSize: 13, fontWeight: 700, color: '#ef4444' }}>
                        <span>Qoldiq qarz:</span>
                        <span>{formatMoney(entry.remaining_amount)} so'm</span>
                      </div>
                    )}
                  </>
                ) : (
                  <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 13, fontWeight: 600, color: '#475569' }}>
                      <span>Jami qarz:</span>
                      <span style={{ color: '#0f172a' }}>{formatMoney(entry.total_amount)} so'm</span>
                    </div>
                    
                    {entry.paid_amount > 0 && (
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 13, fontWeight: 600, color: '#475569' }}>
                        <span>Umumiy to'langan:</span>
                        <span style={{ color: '#16a34a' }}>{formatMoney(entry.paid_amount)} so'm</span>
                      </div>
                    )}
                    
                    {entry.paid_at && (
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6, fontSize: 13, fontWeight: 600, color: '#475569' }}>
                        <span>Oxirgi to'lov sanasi:</span>
                        <span style={{ color: '#0f172a' }}>{formatDate(entry.paid_at)}</span>
                      </div>
                    )}

                    {entry.remaining_amount > 0 && (
                      <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, paddingTop: 8, borderTop: '1px dashed #cbd5e1', fontSize: 14, fontWeight: 700, color: '#ef4444' }}>
                        <span>Qoldiq qarz:</span>
                        <span>{formatMoney(entry.remaining_amount)} so'm</span>
                      </div>
                    )}
                  </>
                )}
              </div>

              {/* Footer */}
              <div style={{ textAlign: 'center', marginTop: 24, paddingTop: 16, borderTop: '1px dashed #cbd5e1', color: '#64748b' }}>
                <p style={{ fontSize: 11, fontWeight: 600, marginBottom: 4 }}>Xizmatingizdan xursandmiz!</p>
                <p style={{ fontSize: 10 }}>Chop etildi: {formatDate(new Date().toISOString())}</p>
                <div style={{ marginTop: 12 }}>
                  <svg style={{ width: '100%', height: 40 }} viewBox="0 0 200 40" preserveAspectRatio="none">
                    <rect width="100%" height="100%" fill="#f1f5f9" />
                    {Array.from({ length: 40 }).map((_, i) => (
                      <rect key={i} x={i * 5} y={0} width={Math.random() * 3 + 1} height="40" fill="#0f172a" />
                    ))}
                  </svg>
                  <p style={{ fontSize: 9, marginTop: 4, letterSpacing: 2 }}>{receiptId}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
