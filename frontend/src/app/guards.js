import { redirect } from '@tanstack/react-router';
import { store } from '@/store';
import { setRedirectTo } from '@/store/uiSlice';

export function requireAuth({ location }) {
  const { status } = store.getState().auth;
  if (status === 'guest') {
    store.dispatch(setRedirectTo(location.href));
    throw redirect({ to: '/login' });
  }
}

export function requireAdmin() {
  const { user } = store.getState().auth;
  if (user?.role !== 'admin') {
    throw redirect({ to: '/dashboard', search: { denied: true } });
  }
}

export function requireGuest() {
  const { status } = store.getState().auth;
  if (status === 'authed') {
    throw redirect({ to: '/dashboard' });
  }
}
