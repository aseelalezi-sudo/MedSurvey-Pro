import '@testing-library/jest-dom/vitest';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

if (!i18n.isInitialized) {
  i18n.use(initReactI18next).init({
    lng: 'ar',
    fallbackLng: 'ar',
    debug: false,
    interpolation: {
      escapeValue: false,
    },
    resources: {
      ar: {
        translation: {
          arabic_language: 'العربية',
          english_language: 'English',

          enable_dark_mode: 'تفعيل الوضع المظلم',
          enable_light_mode: 'تفعيل الوضع المضيء',

          fatal_error_title: 'حدث خطأ تقني جسيم',
          fatal_error_description: 'حدث خطأ غير متوقع في النظام',
          refresh_page: 'تحديث الصفحة',
          back_to_home: 'العودة للرئيسية',

          server_error: 'خطأ في الخادم (500)',
          connection_error: 'خطأ في الاتصال',
          network_error: 'خطأ في الاتصال',
          api_error: 'خطأ في الاتصال',
          unknown_error: 'خطأ غير معروف',

          errors: {
            fatal: {
              title: 'حدث خطأ تقني جسيم',
              description: 'حدث خطأ غير متوقع في النظام',
            },
            server: 'خطأ في الخادم (500)',
            connection: 'خطأ في الاتصال',
            network: 'خطأ في الاتصال',
            api: 'خطأ في الاتصال',
            unknown: 'خطأ غير معروف',
          },

          theme: {
            enable_dark_mode: 'تفعيل الوضع المظلم',
            enable_light_mode: 'تفعيل الوضع المضيء',
          },

          language: {
            arabic: 'العربية',
            english: 'English',
          },
        },
      },

      en: {
        translation: {
          arabic_language: 'العربية',
          english_language: 'English',

          enable_dark_mode: 'تفعيل الوضع المظلم',
          enable_light_mode: 'تفعيل الوضع المضيء',

          fatal_error_title: 'حدث خطأ تقني جسيم',
          fatal_error_description: 'An unexpected system error occurred',
          refresh_page: 'Refresh page',
          back_to_home: 'Back to home',

          server_error: 'خطأ في الخادم (500)',
          connection_error: 'خطأ في الاتصال',
          network_error: 'Connection error',
          api_error: 'Connection error',
          unknown_error: 'Unknown error',

          errors: {
            fatal: {
              title: 'حدث خطأ تقني جسيم',
              description: 'An unexpected system error occurred',
            },
            server: 'خطأ في الخادم (500)',
            connection: 'خطأ في الاتصال',
            network: 'Connection error',
            api: 'Connection error',
            unknown: 'Unknown error',
          },

          theme: {
            enable_dark_mode: 'تفعيل الوضع المظلم',
            enable_light_mode: 'تفعيل الوضع المضيء',
          },

          language: {
            arabic: 'العربية',
            english: 'English',
          },
        },
      },
    },
  });
}

if (!globalThis.localStorage) {
  const storage = new Map<string, string>();

  Object.defineProperty(globalThis, 'localStorage', {
    value: {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => storage.set(key, value),
      removeItem: (key: string) => storage.delete(key),
      clear: () => storage.clear(),
      key: (index: number) => Array.from(storage.keys())[index] ?? null,
      get length() {
        return storage.size;
      },
    },
    configurable: true,
  });
}
