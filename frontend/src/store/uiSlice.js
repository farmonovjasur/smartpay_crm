import { createSlice } from '@reduxjs/toolkit';

/**
 * localStorage'dan saqlangan tema rejimini o'qiydi.
 * Agar foydalanuvchi hech qachon o'zgartirmagan bo'lsa, tizim (OS) rejimiga qaraydi.
 * @returns {'light'|'dark'}
 */
function getInitialTheme() {
  try {
    const stored = localStorage.getItem('smartpay_theme');
    if (stored === 'dark' || stored === 'light') return stored;
  } catch {
    // localStorage mavjud emas yoki ruxsat yo'q — default qiymati ishlatiladi.
  }
  // OS preference'ga qarang
  if (typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }
  return 'light';
}

const uiSlice = createSlice({
  name: 'ui',
  initialState: {
    /** Desktop sidebar — expanded (true, 260px) yoki collapsed (false, 72px). */
    sidebarOpen: true,
    /** Mobile drawer (<lg) — sidebar overlay ko'rinishi yoki yopiq. */
    mobileMenuOpen: false,
    /** @type {string|null} Login'dan keyin qaytariladigan marshrut. */
    redirectTo: null,
    /** Tema rejimi: 'light' yoki 'dark'. */
    theme: getInitialTheme(),
  },
  reducers: {
    toggleSidebar: (state) => {
      state.sidebarOpen = !state.sidebarOpen;
    },
    setSidebarOpen: (state, action) => {
      state.sidebarOpen = action.payload;
    },
    toggleMobileMenu: (state) => {
      state.mobileMenuOpen = !state.mobileMenuOpen;
    },
    setMobileMenuOpen: (state, action) => {
      state.mobileMenuOpen = action.payload;
    },
    setRedirectTo: (state, action) => {
      state.redirectTo = action.payload;
    },
    toggleTheme: (state) => {
      state.theme = state.theme === 'dark' ? 'light' : 'dark';
    },
    setTheme: (state, action) => {
      state.theme = action.payload;
    },
  },
});

export const {
  toggleSidebar,
  setSidebarOpen,
  toggleMobileMenu,
  setMobileMenuOpen,
  setRedirectTo,
  toggleTheme,
  setTheme,
} = uiSlice.actions;
export default uiSlice.reducer;
