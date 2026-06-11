// Feature: smartpay-crm-frontend, Property 12: mapFieldErrors hech bir xato yo'qolmaydi
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { mapFieldErrors } from '../lib/serverErrors';

describe('mapFieldErrors — property-based', () => {
  it("barcha xatolar taqsimlanadi: mavjud field yoki root ga", () => {
    fc.assert(
      fc.property(
        fc.dictionary(
          fc.stringMatching(/^[a-z_]{1,10}$/),
          fc.array(fc.string({ minLength: 1, maxLength: 50 }), { minLength: 1, maxLength: 3 })
        ),
        fc.array(fc.stringMatching(/^[a-z_]{1,10}$/), { maxLength: 5 }),
        (errors, formFields) => {
          const result = mapFieldErrors(errors, formFields);
          const inputKeys = Object.keys(errors);
          const mappedToFields = inputKeys.filter((k) => formFields.includes(k));
          const unmapped = inputKeys.filter((k) => !formFields.includes(k));

          for (const k of mappedToFields) {
            expect(result[k]).toBeTruthy();
          }
          if (unmapped.length > 0) {
            expect(result.root).toBeTruthy();
          }
        }
      ),
      { numRuns: 150 }
    );
  });

  it("null/undefined kiritilsa bo'sh object qaytaradi", () => {
    fc.assert(
      fc.property(fc.oneof(fc.constant(null), fc.constant(undefined)), (errors) => {
        const result = mapFieldErrors(errors, ['name', 'inn']);
        expect(Object.keys(result)).toHaveLength(0);
      }),
      { numRuns: 100 }
    );
  });
});
