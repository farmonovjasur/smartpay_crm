// Feature: smartpay-crm-frontend, Property 10: forma validatorlari
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { validators } from '../lib/validation';

describe('validators — property-based', () => {
  it("inn: 9 yoki 14 raqamli stringni qabul qiladi", () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.stringMatching(/^\d{9}$/),
          fc.stringMatching(/^\d{14}$/)
        ),
        (inn) => { expect(validators.inn(inn)).toBeNull(); }
      ),
      { numRuns: 100 }
    );
  });

  it("inn: boshqa uzunlik yoki harf bo'lsa rad etadi", () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.stringMatching(/^\d{1,8}$/),
          fc.stringMatching(/^\d{10,13}$/),
          fc.stringMatching(/^\d{15,20}$/),
          fc.constant('abc123456')
        ),
        (v) => { expect(validators.inn(v)).toBeTruthy(); }
      ),
      { numRuns: 100 }
    );
  });

  it("phone: +998 dan boshlanib 9 raqam davom etsa qabul", () => {
    fc.assert(
      fc.property(fc.stringMatching(/^\+998\d{9}$/), (phone) => {
        expect(validators.phone(phone)).toBeNull();
      }),
      { numRuns: 100 }
    );
  });

  it("product_count: 1-1000000 butun son qabul", () => {
    fc.assert(
      fc.property(fc.integer({ min: 1, max: 1_000_000 }), (n) => {
        expect(validators.product_count(n)).toBeNull();
      }),
      { numRuns: 100 }
    );
  });

  it("product_count: 0, manfiy, kasr rad etiladi", () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.integer({ min: -100, max: 0 }),
          fc.integer({ min: 1_000_001, max: 2_000_000 }),
          fc.double({ min: 0.1, max: 999.9, noNaN: true }).filter((n) => !Number.isInteger(n))
        ),
        (v) => { expect(validators.product_count(v)).toBeTruthy(); }
      ),
      { numRuns: 100 }
    );
  });

  it("email: oddiy email formatni qabul qiladi", () => {
    fc.assert(
      fc.property(
        fc.tuple(
          fc.stringMatching(/^[a-z]{1,10}$/),
          fc.stringMatching(/^[a-z]{1,10}$/),
          fc.constantFrom('com', 'uz', 'org')
        ),
        ([user, domain, tld]) => {
          expect(validators.email(`${user}@${domain}.${tld}`)).toBeNull();
        }
      ),
      { numRuns: 100 }
    );
  });

  it("period: YYYY-MM formatini qabul qiladi", () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 2020, max: 2099 }),
        fc.integer({ min: 1, max: 12 }),
        (year, month) => {
          const period = `${year}-${String(month).padStart(2, '0')}`;
          expect(validators.period(period)).toBeNull();
        }
      ),
      { numRuns: 100 }
    );
  });

  it("name: 1-255 uzunlikdagi stringni qabul qiladi", () => {
    fc.assert(
      fc.property(fc.string({ minLength: 1, maxLength: 255 }), (name) => {
        expect(validators.name(name)).toBeNull();
      }),
      { numRuns: 100 }
    );
  });
});
