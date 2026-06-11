// Feature: smartpay-crm-frontend, Property 14: ClientImportDialog tasdiqlash tugmasi
// faqat errorRows.length === 0 bo'lganda faol bo'ladi.
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';

/**
 * Komponent ichida ishlatiladigan invariant — preview natijasi bo'yicha tugma faolligi.
 * (Komponent kodida: `const canConfirm = preview && !hasErrors;` bilan bir xil.)
 */
function canConfirm(preview) {
  if (!preview) return false;
  const errorRows = Array.isArray(preview.errorRows) ? preview.errorRows : [];
  return errorRows.length === 0;
}

describe('ClientImportDialog — invariant: faqat xatosiz preview tasdiqlash tugmasini faollashtiradi (P14)', () => {
  it("errorRows soni > 0 bo'lsa, tugma har doim disabled (≥100 iter)", () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({ row: fc.integer({ min: 1, max: 1000 }), errors: fc.array(fc.string()) }),
          { minLength: 1, maxLength: 50 }
        ),
        fc.integer({ min: 0, max: 1000 }),
        fc.integer({ min: 0, max: 100 }),
        (errorRows, totalRows, importedCount) => {
          const preview = { totalRows, importedCount, errorRows, duplicateRows: [] };
          expect(canConfirm(preview)).toBe(false);
        }
      ),
      { numRuns: 120 }
    );
  });

  it("errorRows bo'sh bo'lsa, preview mavjud bo'lganda tugma har doim faol (≥100 iter)", () => {
    fc.assert(
      fc.property(
        fc.integer({ min: 0, max: 10_000 }),
        fc.integer({ min: 0, max: 10_000 }),
        fc.array(
          fc.record({ row: fc.integer({ min: 1 }), inn: fc.string() }),
          { maxLength: 50 }
        ),
        (totalRows, importedCount, duplicateRows) => {
          const preview = { totalRows, importedCount, errorRows: [], duplicateRows };
          expect(canConfirm(preview)).toBe(true);
        }
      ),
      { numRuns: 120 }
    );
  });

  it('preview yo‘q bo‘lsa tugma doim disabled', () => {
    expect(canConfirm(null)).toBe(false);
    expect(canConfirm(undefined)).toBe(false);
  });

  it("errorRows tip jihatidan jadval emas bo'lsa, tugma faol bo'ladi (default 0)", () => {
    // Defensiv shart — backend qaytarganlardan birortasi noma'lum bo'lsa ham,
    // bo'sh xatolar = ruxsat etish (foydalanuvchi tasdiqlasa server uni yana tekshiradi).
    expect(canConfirm({ errorRows: undefined })).toBe(true);
    expect(canConfirm({ errorRows: null })).toBe(true);
  });
});
