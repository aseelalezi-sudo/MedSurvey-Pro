import { Sun, Moon } from 'lucide-react';
import { useThemeStore } from '../store/useThemeStore';

export default function ThemeToggle() {
  const { theme, toggleTheme } = useThemeStore();

  return (
    <button
      onClick={toggleTheme}
      type="button"
      className="p-2.5 bg-gray-50 dark:bg-slate-800/60 border border-gray-200/50 dark:border-slate-700/50 rounded-xl text-gray-500 dark:text-slate-300 hover:text-teal-600 dark:hover:text-teal-400 hover:bg-white dark:hover:bg-slate-800 hover:shadow-md transition-all cursor-pointer relative group overflow-hidden"
      title={theme === 'light' ? 'تفعيل الوضع المظلم' : 'تفعيل الوضع المضيء'}
    >
      <div className="relative w-5 h-5 flex items-center justify-center">
        {theme === 'light' ? (
          <Moon className="w-5 h-5 text-indigo-600 animate-scale-in" />
        ) : (
          <Sun className="w-5 h-5 text-amber-400 animate-scale-in" />
        )}
      </div>
    </button>
  );
}
