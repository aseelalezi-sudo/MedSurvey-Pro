import { render, screen, fireEvent } from '@testing-library/react';
import LanguageSwitcher from '../../components/LanguageSwitcher';
import { vi, describe, it, expect } from 'vitest';

// Mock react-i18next
const mockChangeLanguage = vi.fn().mockResolvedValue({});
let mockLanguage = 'ar';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (str: string) => str,
    i18n: {
      language: mockLanguage,
      changeLanguage: mockChangeLanguage,
    },
  }),
}));

vi.mock('../../store/useThemeStore', () => ({
  useThemeStore: () => ({
    theme: 'light',
    toggleTheme: vi.fn(),
  }),
}));

// Mock ThemeToggle so we focus testing strictly on LanguageSwitcher
vi.mock('./ThemeToggle', () => ({
  default: () => <div data-testid="theme-toggle">ThemeToggle</div>,
}));

describe('LanguageSwitcher Component', () => {
  it('renders correctly in Arabic displaying English option', () => {
    mockLanguage = 'ar';
    render(<LanguageSwitcher />);
    
    const button = screen.getByRole('button', { name: 'English' });
    expect(button).toBeInTheDocument();
    expect(screen.getByText('English')).toBeInTheDocument();
  });

  it('renders correctly in English displaying Arabic option', () => {
    mockLanguage = 'en';
    render(<LanguageSwitcher />);
    
    const button = screen.getByRole('button', { name: 'العربية' });
    expect(button).toBeInTheDocument();
    expect(screen.getByText('العربية')).toBeInTheDocument();
  });

  it('triggers language toggle and updates document properties', () => {
    mockLanguage = 'ar';
    render(<LanguageSwitcher />);
    
    const button = screen.getByRole('button', { name: 'English' });
    fireEvent.click(button);
    
    expect(mockChangeLanguage).toHaveBeenCalledWith('en');
    expect(document.documentElement.dir).toBe('ltr');
    expect(document.documentElement.lang).toBe('en');
  });
});
