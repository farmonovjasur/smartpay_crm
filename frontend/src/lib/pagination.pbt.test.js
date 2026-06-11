// Feature: smartpay-crm-frontend, Property 8: pagination matematik xossalari
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { computeTotalPages, clampPage, normalizePage } from '../lib/pagination';

describe('pagination — property-based', () => {
  it('computeTotalPages har doim >= 1', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 0, max: 100_000 }),
        fc.integer({ min: 1, max: 100 }),
        (total, pageSize) => {
          expect(computeTotalPages(total, pageSize)).toBeGreaterThanOrEqual(1);
        }
      ),
      { numRuns: 150 }
    );
  });

  it('computeTotalPages * pageSize >= total', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 0, max: 100_000 }),
        fc.integer({ min: 1, max: 100 }),
        (total, pageSize) => {
          const pages = computeTotalPages(total, pageSize);
          expect(pages * pageSize).toBeGreaterThanOrEqual(total);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('clampPage har doim [1, totalPages] orasida', () => {
    fc.assert(
      fc.property(
        fc.integer({ min: -100, max: 200 }),
        fc.integer({ min: 1, max: 100 }),
        (page, totalPages) => {
          const result = clampPage(page, totalPages);
          expect(result).toBeGreaterThanOrEqual(1);
          expect(result).toBeLessThanOrEqual(totalPages);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('normalizePage har doim {data, total, page, pageSize} qaytaradi', () => {
    fc.assert(
      fc.property(
        fc.record({
          items: fc.array(fc.integer()),
          total: fc.integer({ min: 0 }),
          page: fc.integer({ min: 1, max: 100 }),
          pageSize: fc.integer({ min: 1, max: 100 }),
        }),
        (res) => {
          const norm = normalizePage(res);
          expect(Array.isArray(norm.data)).toBe(true);
          expect(typeof norm.total).toBe('number');
          expect(typeof norm.page).toBe('number');
          expect(typeof norm.pageSize).toBe('number');
        }
      ),
      { numRuns: 100 }
    );
  });
});
