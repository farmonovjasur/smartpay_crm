// Feature: smartpay-crm-frontend, Property 11: ClientForm validatsiyasi noto‘g‘ri kiritma uchun
// hech qachon so‘rov yubormaydi (sof mantiq darajasida — validators har doim msg qaytaradi).
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { validators } from '@/lib/validation';

const FIELDS = ['inn', 'phone', 'product_count', 'service_date', 'name'];

/** Bitta forma kiritmasini barcha validatorlar orqali tekshiradi. */
function isFormValid(values) {
  for (const f of FIELDS) {
    const v = validators[f]?.(values[f]);
    if (v !== null) return false;
  }
  return true;
}

describe('ClientForm — invariant: invalid kiritma yuborilmaydi (P11)', () => {
  it("invalid INN bilan forma har doim invalid (≥100 iter)", () => {
    fc.assert(
      fc.property(
        // Atayin invalid INN — 9 yoki 14 raqam EMAS.
        fc.oneof(
          fc.string({ minLength: 0, maxLength: 8 }).filter((s) => !/^\d{9}$/.test(s)),
          fc.string({ minLength: 10, maxLength: 13 }).filter((s) => !/^\d{14}$/.test(s)),
          fc.string({ minLength: 15, maxLength: 30 })
        ),
        fc.string({ minLength: 1, maxLength: 200 }), // valid name
        fc.constantFrom('+998901234567', '+998935550101'), // valid phone
        fc.integer({ min: 1, max: 1000 }), // valid product_count
        fc.constant('2026-01-01'), // valid date
        (inn, name, phone, productCount, serviceDate) => {
          const values = { inn, name, phone, product_count: productCount, service_date: serviceDate };
          expect(isFormValid(values)).toBe(false);
        }
      ),
      { numRuns: 120 }
    );
  });

  it('invalid phone bilan forma har doim invalid', () => {
    fc.assert(
      fc.property(
        fc.string({ maxLength: 30 }).filter((s) => !/^\+998\d{9}$/.test(s)),
        (phone) => {
          const values = {
            inn: '123456789',
            name: 'Test',
            phone,
            product_count: 1,
            service_date: '2026-01-01',
          };
          expect(isFormValid(values)).toBe(false);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('product_count < 1 yoki > 1_000_000 bilan forma har doim invalid', () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.integer({ min: -1000, max: 0 }),
          fc.integer({ min: 1_000_001, max: 10_000_000 }),
          // 32-bit float chegaralari, butun son emas — Number.isInteger == false.
          fc.float({ min: Math.fround(0.1), max: Math.fround(0.9), noNaN: true })
        ),
        (productCount) => {
          const values = {
            inn: '123456789',
            name: 'Test',
            phone: '+998901234567',
            product_count: productCount,
            service_date: '2026-01-01',
          };
          expect(isFormValid(values)).toBe(false);
        }
      ),
      { numRuns: 100 }
    );
  });

  it("kelajak sana bilan forma har doim invalid", () => {
    fc.assert(
      fc.property(
        // Vaqt mintaqasi (UTC vs lokal) chegarasini chetlab o'tish uchun ≥2 kun olamiz.
        fc.integer({ min: 2, max: 365 * 50 }),
        (daysAhead) => {
          const future = new Date();
          future.setHours(12, 0, 0, 0);
          future.setDate(future.getDate() + daysAhead);
          const iso = future.toISOString().slice(0, 10);
          const values = {
            inn: '123456789',
            name: 'Test',
            phone: '+998901234567',
            product_count: 1,
            service_date: iso,
          };
          expect(isFormValid(values)).toBe(false);
        }
      ),
      { numRuns: 100 }
    );
  });

  it("to'liq valid kiritma uchun forma valid (kontrol)", () => {
    const values = {
      inn: '123456789',
      name: 'Test Client',
      phone: '+998901234567',
      product_count: 1,
      service_date: '2024-01-01',
    };
    expect(isFormValid(values)).toBe(true);
  });
});
