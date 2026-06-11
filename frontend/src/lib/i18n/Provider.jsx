import { useState, useCallback, useMemo, useEffect } from 'react';
import { I18nContext, STORAGE_KEY, getStoredLocale } from './context';

/**
 * I18n Provider — ilovani o'rab turadi.
 */
export function I18nProvider({ children }) {
  const [locale, setLocaleState] = useState(getStoredLocale);

  const setLocale = useCallback((newLocale) => {
    if (newLocale !== 'uz' && newLocale !== 'ru') return;
    setLocaleState(newLocale);
    try {
      localStorage.setItem(STORAGE_KEY, newLocale);
    } catch {
      // ignore
    }
  }, []);

  // HTML lang atributi
  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const value = useMemo(() => ({ locale, setLocale }), [locale, setLocale]);

  return (
    <I18nContext.Provider value={value}>
      {children}
    </I18nContext.Provider>
  );
}
