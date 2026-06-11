// Feature: smartpay-crm-frontend, Property: handleMutationError tartibi (R15)
import { describe, it, expect, vi, beforeEach } from 'vitest';
import fc from 'fast-check';

const showErrorMock = vi.fn();
vi.mock('./toast', () => ({
  showError: (...args) => showErrorMock(...args),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
}));

const { handleMutationError } = await import('./mutationErrors');

function buildErr(status, body, hasResponse = true) {
  if (!hasResponse) return { response: undefined };
  return { response: { status, data: body } };
}

describe('handleMutationError', () => {
  beforeEach(() => {
    showErrorMock.mockReset();
  });

  it('422 + errors → setError chaqiriladi va Toast chiqmaydi', () => {
    const setError = vi.fn();
    handleMutationError(
      buildErr(422, { errors: { name: ['Ism majburiy'], email: ['Email noto‘g‘ri'] } }),
      { setError, fields: ['name', 'email'] }
    );
    expect(setError).toHaveBeenCalledWith('name', { message: 'Ism majburiy' });
    expect(setError).toHaveBeenCalledWith('email', { message: 'Email noto‘g‘ri' });
    expect(showErrorMock).not.toHaveBeenCalled();
  });

  it('422 — formaga mos kelmagan maydon `root` orqali Toast bilan ko‘rsatiladi', () => {
    const setError = vi.fn();
    handleMutationError(
      buildErr(422, { errors: { unknown_field: ['Xato'] } }),
      { setError, fields: ['name'] }
    );
    expect(showErrorMock).toHaveBeenCalled();
  });

  it('409 + conflictField → setError chaqiriladi (Toast emas)', () => {
    const setError = vi.fn();
    handleMutationError(
      buildErr(409, {}),
      { setError, fields: ['inn'], conflictField: 'inn', statusMessages: { 409: "Bu INN mavjud" } }
    );
    expect(setError).toHaveBeenCalledWith('inn', { message: "Bu INN mavjud" });
    expect(showErrorMock).not.toHaveBeenCalled();
  });

  it("409 conflictField bo'lmasa Toast bilan ko'rsatiladi", () => {
    handleMutationError(buildErr(409, {}), {
      statusMessages: { 409: "Bu yozuv allaqachon mavjud" },
    });
    expect(showErrorMock).toHaveBeenCalledWith("Bu yozuv allaqachon mavjud");
  });

  it("statusMessages funksiya null qaytarsa fallback Toast'ga o'tadi", () => {
    handleMutationError(buildErr(409, {}), {
      statusMessages: { 409: () => null },
    });
    expect(showErrorMock).toHaveBeenCalled();
    // Fallback xabarda 409 ga mos defaul matn (errors.js dan).
    const arg = String(showErrorMock.mock.calls[0]?.[0] || '');
    expect(arg.length).toBeGreaterThan(0);
  });

  it("ko'rsatmagan status (500) doim Toast bilan ko'rsatiladi", () => {
    handleMutationError(buildErr(500, {}));
    expect(showErrorMock).toHaveBeenCalledTimes(1);
  });

  it('tarmoq xatosi (response yo‘q) → Toast', () => {
    handleMutationError(buildErr(null, null, false));
    expect(showErrorMock).toHaveBeenCalledTimes(1);
  });

  it("har qanday status uchun bitta yo'nalish bo'yicha sodir bo'ladi (no double notify)", () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.constant(400),
          fc.constant(403),
          fc.constant(409),
          fc.constant(413),
          fc.constant(429),
          fc.constant(500),
          fc.constant(503)
        ),
        (status) => {
          showErrorMock.mockReset();
          handleMutationError(buildErr(status, {}));
          // Toast EXAKT bir marta chaqiriladi (deduplikatsiya emas, balki yo'nalish bittaligi).
          expect(showErrorMock).toHaveBeenCalledTimes(1);
        }
      ),
      { numRuns: 100 }
    );
  });
});
