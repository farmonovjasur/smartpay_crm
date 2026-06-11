import { useTheme } from '@/hooks/useTheme';

export function AuthLayout({ children }) {
  // Theme hookini chaqirib, html'ga dark class qo'shilishini ta'minlaymiz.
  useTheme();

  return (
    <div className="flex min-h-screen items-center justify-center bg-[var(--bg-light)] px-4">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-primary">SmartPay CRM</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-1">Tizimga kirish</p>
        </div>
        {children}
      </div>
    </div>
  );
}
