// Feature: smartpay-crm-frontend, Property 16: formatMoney minglik ajratgich va aniqlik
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { formatMoney } from '../lib/money';

describe('formatMoney — property-based', () => {
  it("natija raqamlarni o'zgartirmaydi (faqat bo'shliq qo'shadi)", () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 0, max: 999_999_999 }).map((n) => `${n}.00`),
        (input) => {
          const result = formatMoney(input);
          expect(result.replace(/ /g, '')).toBe(input);
        }
      ),
      { numRuns: 150 }
    );
  });

  it("har doim string qaytaradi, hech qachon bo'sh emas", () => {
    fc.assert(
      fc.property(fc.oneof(fc.string(), fc.constant(null), fc.constant(undefined), fc.constant('0')), (input) => {
        const result = formatMoney(input);
        expect(typeof result).toBe('string');
        expect(result.length).toBeGreaterThan(0);
      }),
      { numRuns: 100 }
    );
  });

  it("kasr qismi o'zgarmaydi", () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 0, max: 999_999 }),
        fc.integer({ min: 0, max: 99 }).map((f) => String(f).padStart(2, '0')),
        (intPart, frac) => {
          const input = `${intPart}.${frac}`;
          const result = formatMoney(input);
          expect(result.endsWith(`.${frac}`)).toBe(true);
        }
      ),
      { numRuns: 100 }
    );
  });
});
