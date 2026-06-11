// Feature: smartpay-crm-frontend, Property 20: mapErrorToMessage total funksiya
import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { mapErrorToMessage } from '../lib/errors';

describe('mapErrorToMessage — property-based', () => {
  it("har doim bo'sh bo'lmagan string qaytaradi (total funksiya)", () => {
    fc.assert(
      fc.property(
        fc.record({
          status: fc.oneof(fc.constantFrom(400, 401, 403, 409, 413, 422, 429, 500, 503, null), fc.integer()),
          isNetwork: fc.boolean(),
          body: fc.oneof(fc.constant(null), fc.constant({}), fc.record({ message: fc.string() })),
        }),
        ({ status, isNetwork, body }) => {
          const msg = mapErrorToMessage({ status, body, isNetwork });
          expect(typeof msg).toBe('string');
          expect(msg.length).toBeGreaterThan(0);
        }
      ),
      { numRuns: 200 }
    );
  });

  it("isNetwork=true bo'lsa tarmoq xabari qaytaradi", () => {
    fc.assert(
      fc.property(fc.anything(), (status) => {
        const msg = mapErrorToMessage({ status, isNetwork: true });
        expect(msg).toContain('Tarmoq');
      }),
      { numRuns: 100 }
    );
  });
});
