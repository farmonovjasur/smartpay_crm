// Feature: smartpay-crm-frontend, Property 17: formatDate DD.MM.YYYY formati
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { formatDate } from '../lib/date';

describe('formatDate — property-based', () => {
  it('yaroqli sana uchun DD.MM.YYYY formatida qaytaradi', () => {
    fc.assert(
      fc.property(
        fc.date({ min: new Date('1970-01-01'), max: new Date('2099-12-31'), noInvalidDate: true }),
        (d) => {
          const result = formatDate(d);
          expect(result).toMatch(/^\d{2}\.\d{2}\.\d{4}$/);
          const [day, month, year] = result.split('.').map(Number);
          expect(day).toBe(d.getDate());
          expect(month).toBe(d.getMonth() + 1);
          expect(year).toBe(d.getFullYear());
        }
      ),
      { numRuns: 150 }
    );
  });

  it("noto'g'ri qiymat uchun bo'sh string qaytaradi", () => {
    fc.assert(
      fc.property(
        fc.oneof(fc.constant(null), fc.constant(undefined), fc.constant(''), fc.constant('abc')),
        (v) => {
          expect(formatDate(v)).toBe('');
        }
      ),
      { numRuns: 100 }
    );
  });
});
