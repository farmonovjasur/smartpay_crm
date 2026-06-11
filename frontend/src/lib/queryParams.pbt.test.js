// Feature: smartpay-crm-frontend, Property 9: buildListParams xossalari
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { buildListParams } from '../lib/queryParams';

describe('buildListParams — property-based', () => {
  it("pageSize har doim [1, 100] orasida", () => {
    fc.assert(
      fc.property(
        fc.integer({ min: -50, max: 500 }),
        (pageSize) => {
          const result = buildListParams({ pageSize });
          expect(result.pageSize).toBeGreaterThanOrEqual(1);
          expect(result.pageSize).toBeLessThanOrEqual(100);
        }
      ),
      { numRuns: 150 }
    );
  });

  it("page default 1, sort default id_desc", () => {
    fc.assert(
      fc.property(fc.record({ search: fc.string() }), (filters) => {
        const result = buildListParams(filters);
        expect(result.page).toBe(1);
        expect(result.sort).toBe('id_desc');
      }),
      { numRuns: 100 }
    );
  });

  it("bo'sh/null qiymatlar natijadan olib tashlanadi", () => {
    fc.assert(
      fc.property(
        fc.constantFrom('', null, undefined),
        (emptyVal) => {
          const result = buildListParams({ search: emptyVal, status: 'faol' });
          expect(result).not.toHaveProperty('search');
          expect(result.status).toBe('faol');
        }
      ),
      { numRuns: 100 }
    );
  });
});
