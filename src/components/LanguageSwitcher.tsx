import { useTranslation } from 'react-i18next';
import { Globe } from 'lucide-react';
import ThemeToggle from './ThemeToggle';

export default function LanguageSwitcher() {
  const { i18n } = useTranslation();
  const nextLanguageLabel = i18n.language === 'ar' ? 'English' : 'العربية';

  const toggleLanguage = () => {
    const newLng = i18n.language === 'ar' ? 'en' : 'ar';
    i18n.changeLanguage(newLng);
    document.documentElement.dir = newLng === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = newLng;
  };

  return (
    <div className="flex items-center gap-2">
      <button
        onClick={toggleLanguage}
        type="button"
        aria-label={nextLanguageLabel}
        title={nextLanguageLabel}
        className="flex items-center justify-center sm:justify-start gap-2 p-2 sm:px-3 sm:py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800/60 transition-colors text-sm font-medium text-gray-600 dark:text-slate-300 cursor-pointer"
      >
        <Globe className="w-4 h-4 text-teal-600 dark:text-teal-400" />
        <span className="hidden sm:inline">{nextLanguageLabel}</span>
      </button>
      <ThemeToggle />
    </div>
  );
}
