import { create } from 'zustand';

export type Theme = 'light' | 'dark';

interface ThemeState {
  theme: Theme;
  toggleTheme: () => void;
}

const getInitialTheme = (): Theme => {
  if (typeof window === 'undefined' || typeof localStorage === 'undefined') return 'light';
  const saved = localStorage.getItem('theme');
  if (saved === 'dark' || (!saved && typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches)) {
    if (typeof document !== 'undefined') {
      document.documentElement.classList.add('dark');
    }
    return 'dark';
  }
  if (typeof document !== 'undefined') {
    document.documentElement.classList.remove('dark');
  }
  return 'light';
};

export const useThemeStore = create<ThemeState>((set) => ({
  theme: getInitialTheme(),
  toggleTheme: () => set((state) => {
    const nextTheme: Theme = state.theme === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', nextTheme);
    if (typeof document !== 'undefined') {
      if (nextTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    }
    return { theme: nextTheme };
  }),
}));
