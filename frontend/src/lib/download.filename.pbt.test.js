// Feature: smartpay-crm-frontend, Property 15: parseFilename hech qachon bo'sh string emas
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { parseFilename } from '../lib/download';

describe('parseFilename — property-based', () => {
  it("hech qachon bo'sh string qaytarmaydi", () => {
    fc.assert(
      fc.property(
        fc.oneof(fc.string(), fc.constant(null), fc.constant(undefined), fc.constant('')),
        (header) => {
          const result = parseFilename(header);
          expect(typeof result).toBe('string');
          expect(result.length).toBeGreaterThan(0);
        }
      ),
      { numRuns: 150 }
    );
  });

  it("filename=\"...\" mavjud bo'lsa, shu nomni ajratadi", () => {
    fc.assert(
      fc.property(
        fc.stringMatching(/^[a-zA-Z0-9_-]{1,20}\.[a-z]{2,4}$/),
        (name) => {
          const header = `attachment; filename="${name}"`;
          expect(parseFilename(header)).toBe(name);
        }
      ),
      { numRuns: 100 }
    );
  });

  it("hech qanday filename bo'lmasa fallback qaytaradi", () => {
    fc.assert(
      fc.property(
        fc.stringMatching(/^[a-z]{1,10}$/),
        fc.stringMatching(/^[a-z]{1,10}\.[a-z]{2,4}$/),
        (header, fallback) => {
          expect(parseFilename(header, fallback)).toBe(fallback);
        }
      ),
      { numRuns: 100 }
    );
  });
});
