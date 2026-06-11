import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

// Dev rejimida backend (Symfony, http://localhost:8000) ni shu origin orqali proksilaymiz.
// Shunda frontend va backend BIR origin'da bo'lib, CORS muammosi yo'qoladi va
// JWT httpOnly cookie'lar birinchi-tomon (first-party) sifatida saqlanadi va yuboriladi.
const BACKEND_TARGET = process.env.VITE_DEV_BACKEND || 'http://localhost:8000';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
    extensions: ['.mjs', '.js', '.jsx', '.ts', '.tsx', '.json'],
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: BACKEND_TARGET,
        changeOrigin: true,
        secure: false,
        configure: (proxy) => {
          // Dev (http://localhost) da brauzer "Secure" cookie'larni saqlamaydi.
          // Backend cookie'larni Secure + SameSite=None bilan yuboradi — shu sababli
          // dev proksisida Set-Cookie'dan "Secure" va "SameSite=None" ni olib tashlaymiz,
          // shunda cookie'lar http://localhost da saqlanadi. (Faqat dev uchun!)
          proxy.on('proxyRes', (proxyRes) => {
            const setCookie = proxyRes.headers['set-cookie'];
            if (setCookie) {
              proxyRes.headers['set-cookie'] = setCookie.map((c) =>
                c
                  .replace(/;\s*Secure/gi, '')
                  .replace(/;\s*SameSite=None/gi, '; SameSite=Lax')
              );
            }
          });
        },
      },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.js'],
    css: false,
  },
});
