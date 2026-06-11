import { useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { useMutation } from '@tanstack/react-query';
import { setAuthLoading, setUser, clearAuth } from '@/store/authSlice';
import { router } from '@/app/router';
import { authApi } from './api';

/** Redux auth holatini o'qish */
export function useAuth() {
  const { user, status } = useSelector((s) => s.auth);
  return {
    user,
    status,
    isAuthed: status === 'authed',
    isAdmin: user?.role === 'admin',
    isResolving: status === 'idle' || status === 'loading',
  };
}

/** Login mutatsiyasi — backend {user} qaytaradi; sessiya cookie'larda saqlanadi */
export function useLogin() {
  const dispatch = useDispatch();
  return useMutation({
    mutationFn: authApi.login,
    onSuccess: (data) => {
      dispatch(setUser(data.user));
    },
  });
}

/** Logout — natijadan qat'i nazar auth tozalanadi va login'ga yo'naltiriladi */
export function useLogout() {
  const dispatch = useDispatch();
  return useMutation({
    mutationFn: authApi.logout,
    onSettled: () => {
      dispatch(clearAuth());
      router.navigate({ to: '/login' });
    },
  });
}

/**
 * Bootstrap — ilova yuklanganda GET /api/auth/me orqali sessiyani tiklaydi.
 * 200 → setUser, 401/xato → clearAuth.
 * Module-level bayroq StrictMode (dev) da ikki marta ishga tushishini oldini oladi.
 */
let bootstrapStarted = false;

export function useBootstrap() {
  const dispatch = useDispatch();
  const status = useSelector((s) => s.auth.status);

  useEffect(() => {
    if (status !== 'idle' || bootstrapStarted) return;
    bootstrapStarted = true;
    dispatch(setAuthLoading());
    authApi
      .me()
      .then((data) => dispatch(setUser(data.user)))
      .catch(() => dispatch(clearAuth()));
  }, [status, dispatch]);
}
