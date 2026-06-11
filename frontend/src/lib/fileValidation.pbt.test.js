// Feature: smartpay-crm-frontend, Property 13: validateImportFile .xlsx va <=5MB
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { validateImportFile } from '../lib/fileValidation';

describe('validateImportFile — property-based', () => {
  it(".xlsx va <=5MB bo'lsa valid", () => {
    fc.assert(
      fc.property(
        fc.string({ minLength: 1, maxLength: 50 }),
        fc.integer({ min: 1, max: 5_242_880 }),
        (name, size) => {
          const result = validateImportFile({ filename: `${name}.xlsx`, size });
          expect(result.valid).toBe(true);
          expect(result.error).toBeUndefined();
        }
      ),
      { numRuns: 150 }
    );
  });

  it(".xlsx bo'lmagan fayllar rad etiladi", () => {
    fc.assert(
      fc.property(
        fc.constantFrom('.csv', '.xls', '.pdf', '.txt', ''),
        fc.integer({ min: 1, max: 5_242_880 }),
        (ext, size) => {
          const result = validateImportFile({ filename: `file${ext}`, size });
          expect(result.valid).toBe(false);
          expect(result.error).toBeTruthy();
        }
      ),
      { numRuns: 100 }
    );
  });

  it("5MB dan katta fayllar rad etiladi", () => {
    fc.assert(
      fc.property(fc.integer({ min: 5_242_881, max: 100_000_000 }), (size) => {
        const result = validateImportFile({ filename: 'data.xlsx', size });
        expect(result.valid).toBe(false);
      }),
      { numRuns: 100 }
    );
  });
});
