// Feature: smartpay-crm-frontend, Property 19: parseRetryAfter har doim musbat butun son
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { parseRetryAfter } from '../lib/retryAfter';

describe('parseRetryAfter — property-based', () => {
  it("musbat son kiritilsa, shu sonni qaytaradi", () => {
    fc.assert(
      fc.property(fc.integer({ min: 1, max: 3600 }), (n) => {
        expect(parseRetryAfter(String(n))).toBe(n);
        expect(parseRetryAfter(n)).toBe(n);
      }),
      { numRuns: 150 }
    );
  });

  it("noto'g'ri qiymatlar uchun 60 qaytaradi (hech qachon NaN/manfiy emas)", () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.constant(null), fc.constant(undefined), fc.constant(''),
          fc.constant('abc'), fc.constant('-5'), fc.constant('0'),
          fc.constant(NaN), fc.constant(-1)
        ),
        (v) => {
          const result = parseRetryAfter(v);
          expect(result).toBe(60);
          expect(Number.isFinite(result)).toBe(true);
          expect(result).toBeGreaterThan(0);
        }
      ),
      { numRuns: 100 }
    );
  });
});
