import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';
import ThemeToggle from './ThemeToggle';
import { useSettingsStore } from '../store/useSettingsStore';

export default function LanguageSwitcher() {
  const { i18n } = useTranslation();
  const { settings } = useSettingsStore();

  const currentLanguage = i18n.language?.startsWith('ar') ? 'ar' : 'en';
  const nextLanguage = currentLanguage === 'ar' ? 'en' : 'ar';
  const nextLanguageLabel = currentLanguage === 'ar' ? 'English' : 'العربية';

  const toggleLanguage = () => {
    i18n.changeLanguage(nextLanguage);
    document.documentElement.dir = nextLanguage === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = nextLanguage;
  };

  const showLanguageToggle = settings?.appearance?.showLanguageToggle !== false;

  return (
    <div className="flex items-center gap-2">
      {showLanguageToggle && (
        <button
          onClick={toggleLanguage}
          type="button"
          data-testid="language-switcher"
          aria-label={nextLanguageLabel}
          title={nextLanguageLabel}
          className="flex items-center justify-center sm:justify-start gap-2 p-2 sm:px-3 sm:py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-colors text-sm font-medium text-gray-600 dark:text-slate-300 cursor-pointer"
        >
          <Globe className="w-4 h-4 text-teal-600 dark:text-teal-400" />
          <span className="hidden sm:inline">{nextLanguageLabel}</span>
        </button>
      )}

      <ThemeToggle />
    </div>
  );
}
