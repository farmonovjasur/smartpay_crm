import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PaymentReceipt } from './PaymentReceipt';

describe('PaymentReceipt — taqsimot va chek ko\'rinishi', () => {
  const mockClient = {
    id: 1,
    name: 'Smart Solutions MCHJ',
    inn: '301234567',
    phone: '+998901234567',
    balance: '0.00',
    last_paid_period: '2026-10',
  };

  it('qarz yopilishi va oldindan to\'lov (Iyun, Iyul qarz + Avgust, Sentyabr, Oktyabr oldindan to\'lov) taqsimotini to\'liq aks ettiradi', () => {
    const mockPayment = {
      receipt_id: 'CHK-20260814-000042',
      paid_at: '2026-08-14T11:30:00Z',
      payment_method: 'naqt',
      total_amount: '500000.00',
      debt_amount_paid: '200000.00',
      prepaid_amount: '300000.00',
      balance_added: '0.00',
      client: mockClient,
      debt_items: [
        { type: 'debt', period: '2026-06', period_label: 'Iyun 2026', amount: '100000.00', label: 'Qarz yopildi' },
        { type: 'debt', period: '2026-07', period_label: 'Iyul 2026', amount: '100000.00', label: 'Qarz yopildi' },
      ],
      prepaid_items: [
        { type: 'prepaid', period: '2026-08', period_label: 'Avgust 2026', amount: '100000.00', label: "Oldindan to'lov" },
        { type: 'prepaid', period: '2026-09', period_label: 'Sentyabr 2026', amount: '100000.00', label: "Oldindan to'lov" },
        { type: 'prepaid', period: '2026-10', period_label: 'Oktyabr 2026', amount: '100000.00', label: "Oldindan to'lov" },
      ],
      summary: {
        debt_remaining: '0.00',
        new_balance: '0.00',
        paid_up_to: '2026-10',
        paid_up_to_label: 'Oktyabr 2026',
        months_debt_closed: 2,
        months_prepaid: 3,
        created_by: 'Admin Bekzod',
      },
    };

    render(
      <PaymentReceipt
        open={true}
        onOpenChange={() => {}}
        payment={mockPayment}
        client={mockClient}
      />
    );

    // Chek sarlavhasi va ID
    expect(screen.getByText('TO\'LOV KVITANSIYASI')).toBeInTheDocument();
    expect(screen.getAllByText('CHK-20260814-000042').length).toBeGreaterThanOrEqual(1);

    // Mijoz ma'lumotlari
    expect(screen.getByText('Smart Solutions MCHJ')).toBeInTheDocument();
    expect(screen.getByText('301234567')).toBeInTheDocument();

    // Qarz yopilgan oylar
    expect(screen.getByText(/\[QARZ YOPILDI\]/i)).toBeInTheDocument();
    expect(screen.getByText(/Iyun 2026/)).toBeInTheDocument();
    expect(screen.getByText(/Iyul 2026/)).toBeInTheDocument();

    // Oldindan to'langan oylar
    expect(screen.getByText(/\[OLDINDAN TO'LOV\]/i)).toBeInTheDocument();
    expect(screen.getByText(/Avgust 2026/)).toBeInTheDocument();
    expect(screen.getByText(/Sentyabr 2026/)).toBeInTheDocument();
    expect(screen.getAllByText(/Oktyabr 2026/).length).toBeGreaterThanOrEqual(1);

    // Jami summa
    expect(screen.getByText('500 000.00')).toBeInTheDocument();
    expect(screen.getByText(/0 UZS \(To'liq yopildi\)/i)).toBeInTheDocument();
    expect(screen.getByText(/Oktyabr 2026 gacha/i)).toBeInTheDocument();
  });

  it('faqat yakka oylik to\'lov chekini to\'g\'ri ko\'rsatadi', () => {
    const singlePayment = {
      id: 12,
      amount: '100000.00',
      period: '2026-08',
      method: 'fakt',
      paid_at: '2026-08-14T10:00:00Z',
      is_debt: false,
      _type: 'payment',
    };

    render(
      <PaymentReceipt
        open={true}
        onOpenChange={() => {}}
        payment={singlePayment}
        client={mockClient}
      />
    );

    expect(screen.getByText('Smart Solutions MCHJ')).toBeInTheDocument();
    expect(screen.getByText('Avgust 2026')).toBeInTheDocument();
    expect(screen.getByText('100 000.00')).toBeInTheDocument();
  });
});
