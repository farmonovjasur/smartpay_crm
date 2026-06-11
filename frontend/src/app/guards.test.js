// Feature: smartpay-crm-frontend, Integration: route guardlari (R3.x)
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { store } from '@/store';
import { setUser, clearAuth } from '@/store/authSlice';
import { setRedirectTo } from '@/store/uiSlice';
import { requireAuth, requireAdmin, requireGuest } from './guards';

describe('Route guards', () => {
  beforeEach(() => {
    store.dispatch(clearAuth());
    store.dispatch(setRedirectTo(null));
  });

  it("requireAuth: guest uchun /login ga redirect (R3.1)", () => {
    expect(() => requireAuth({ location: { href: '/dashboard' } })).toThrow();
    // redirectTo Redux'ga saqlanadi.
    expect(store.getState().ui.redirectTo).toBe('/dashboard');
  });

  it("requireAuth: authed user uchun redirect yo'q", () => {
    store.dispatch(setUser({ id: 1, name: 'X', email: 'x@y.uz', role: 'user' }));
    expect(() => requireAuth({ location: { href: '/dashboard' } })).not.toThrow();
  });

  it("requireAdmin: oddiy user uchun /dashboard ga redirect (R3.4)", () => {
    store.dispatch(setUser({ id: 2, name: 'User', email: 'u@y.uz', role: 'user' }));
    expect(() => requireAdmin()).toThrow();
  });

  it("requireAdmin: admin uchun redirect yo'q", () => {
    store.dispatch(setUser({ id: 1, name: 'Admin', email: 'a@y.uz', role: 'admin' }));
    expect(() => requireAdmin()).not.toThrow();
  });

  it("requireGuest: authed user uchun /dashboard'ga redirect", () => {
    store.dispatch(setUser({ id: 1, name: 'X', email: 'x@y.uz', role: 'user' }));
    expect(() => requireGuest()).toThrow();
  });

  it("requireGuest: guest uchun redirect yo'q", () => {
    expect(() => requireGuest()).not.toThrow();
  });
});
