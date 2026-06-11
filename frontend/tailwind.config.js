/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      colors: {
        // CSS variable asosida dinamik ranglar (dark mode uchun)
        'bg-light': 'var(--bg-light)',
        'card-bg': 'var(--card-bg)',
        'navbar-bg': 'var(--navbar-bg)',
        primary: {
          DEFAULT: '#6366F1',
          hover: '#4F46E5',
          bg: '#EEF2FF',
          text: '#4F46E5',
        },
        sidebar: {
          bg: 'var(--sidebar-bg)',
          text: 'var(--sidebar-text)',
          active: '#6366F1',
        },
        success: {
          DEFAULT: '#10B981',
          bg: '#ECFDF5',
          text: '#047857',
        },
        warning: {
          DEFAULT: '#F59E0B',
          bg: '#FFFBEB',
          text: '#D97706',
        },
        danger: {
          DEFAULT: '#EF4444',
          bg: '#FEF2F2',
          text: '#DC2626',
        },
        info: {
          DEFAULT: '#3B82F6',
          bg: '#EFF6FF',
          text: '#2563EB',
        },
        purple: {
          DEFAULT: '#8B5CF6',
          bg: '#F5F3FF',
          text: '#7C3AED',
        },
        teal: {
          DEFAULT: '#14B8A6',
          bg: '#F0FDFA',
          text: '#0D9488',
        },
      },
      borderRadius: {
        btn: '8px',
        card: '12px',
      },
    },
  },
  plugins: [],
};
