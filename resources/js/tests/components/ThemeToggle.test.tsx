import { render, screen, fireEvent } from '@testing-library/react';
import ThemeToggle from '../../components/ThemeToggle';
import { vi, describe, it, expect } from 'vitest';

// Mock useThemeStore
const mockToggleTheme = vi.fn();
let mockCurrentTheme = 'light';

vi.mock('../../store/useThemeStore', () => ({
  useThemeStore: () => ({
    theme: mockCurrentTheme,
    toggleTheme: mockToggleTheme,
  }),
}));

describe('ThemeToggle Component', () => {
  it('renders correctly with light theme', () => {
    mockCurrentTheme = 'light';
    render(<ThemeToggle />);
    
    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('title', 'تفعيل الوضع المظلم');
  });

  it('renders correctly with dark theme', () => {
    mockCurrentTheme = 'dark';
    render(<ThemeToggle />);
    
    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('title', 'تفعيل الوضع المضيء');
  });

  it('triggers toggleTheme when clicked', () => {
    render(<ThemeToggle />);
    
    const button = screen.getByRole('button');
    fireEvent.click(button);
    
    expect(mockToggleTheme).toHaveBeenCalledTimes(1);
  });
});
