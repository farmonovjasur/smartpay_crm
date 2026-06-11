import { useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import { toggleTheme } from '@/store/uiSlice';

/**
 * Dark/Light tema rejimini boshqarish hooki.
 *
 * - Redux'dan `theme` qiymatini o'qiydi.
 * - `<html>` elementiga `dark` klassini qo'shadi/olib tashlaydi.
 * - localStorage'ga saqlaydi.
 * - `toggle()` funksiyasini qaytaradi.
 */
export function useTheme() {
  const dispatch = useDispatch();
  const theme = useSelector((s) => s.ui.theme);
  const isDark = theme === 'dark';

  useEffect(() => {
    const root = document.documentElement;
    if (isDark) {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
    try {
      localStorage.setItem('smartpay_theme', theme);
    } catch {
      // localStorage mavjud emas — e'tibor bermaymiz.
    }
  }, [theme, isDark]);

  const toggle = () => dispatch(toggleTheme());

  return { theme, isDark, toggle };
}
