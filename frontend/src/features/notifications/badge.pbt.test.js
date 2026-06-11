// Feature: smartpay-crm-frontend, Property 18: Bildirishnomalar badge soni = is_read===false soni
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { countUnread } from './api';

describe('countUnread — property-based (P18)', () => {
  it("badge soni har doim is_read===false bo'lgan elementlar soniga teng", () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.integer({ min: 1, max: 1_000_000 }),
            is_read: fc.boolean(),
            title: fc.string(),
          }),
          { maxLength: 200 }
        ),
        (list) => {
          const expected = list.filter((n) => n.is_read === false).length;
          expect(countUnread(list)).toBe(expected);
        }
      ),
      { numRuns: 150 }
    );
  });

  it("`is_read` qiymati true yoki noto'g'ri tip bo'lgan elementlar hisobga olinmaydi", () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.oneof(
            fc.record({ is_read: fc.constant(true) }),
            fc.record({ is_read: fc.constant('false') }), // string emas — false emas
            fc.record({ is_read: fc.constant(0) }), // 0 — false emas
            fc.record({ is_read: fc.constant(null) })
          ),
          { maxLength: 100 }
        ),
        (list) => {
          // Faqat strict false hisobga olinadi.
          expect(countUnread(list)).toBe(0);
        }
      ),
      { numRuns: 100 }
    );
  });

  it("noto'g'ri kiritma uchun 0 qaytaradi (jadval emas, null, undefined)", () => {
    expect(countUnread(null)).toBe(0);
    expect(countUnread(undefined)).toBe(0);
    expect(countUnread('foo')).toBe(0);
    expect(countUnread({ a: 1 })).toBe(0);
  });
});
