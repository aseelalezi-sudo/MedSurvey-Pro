import { render, screen, fireEvent, act } from '@testing-library/react';
import ThankYouPage from '../../components/ThankYouPage';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual as any,
    useNavigate: () => mockNavigate,
  };
});

// Mock react-i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (str: string, fallback?: string) => fallback || str,
    i18n: { language: 'ar', changeLanguage: () => Promise.resolve() },
  }),
}));

// Mock LanguageSwitcher
vi.mock('../../components/LanguageSwitcher', () => ({
  default: () => <div data-testid="language-switcher">LanguageSwitcher</div>,
}));

// Mock useSurveyStore
const mockResetSurveySession = vi.fn();
let mockSelectedTip: string | null = 'شرب الماء بكثرة يقي من الجفاف';
let mockSurveysList = [{ id: 'survey-1', title: 'استبيان العيادات الخارجية', isActive: true }];

vi.mock('../../store/useSurveyStore', () => ({
  useSurveyStore: () => ({
    selectedTip: mockSelectedTip,
    resetSurveySession: mockResetSurveySession,
    surveys: mockSurveysList,
  }),
}));

describe('ThankYouPage Component', () => {
  beforeEach(() => {
    mockSelectedTip = 'شرب الماء بكثرة يقي من الجفاف';
    mockSurveysList = [{ id: 'survey-1', title: 'استبيان العيادات الخارجية', isActive: true }];
    vi.useFakeTimers();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders correctly and shows success messages', () => {
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    expect(screen.getByText('thank_you 🎉')).toBeInTheDocument();
    expect(screen.getByText('تم إرسال استبيانكم بنجاح')).toBeInTheDocument();
    expect(screen.getByText(/شرب الماء بكثرة يقي من الجفاف/)).toBeInTheDocument();
    expect(screen.getByTestId('language-switcher')).toBeInTheDocument();
  });

  it('renders default message if no health tip exists', () => {
    mockSelectedTip = null;
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    expect(screen.getByText('صحتكم أولويتنا')).toBeInTheDocument();
    expect(screen.queryByText('شرب الماء بكثرة يقي من الجفاف')).not.toBeInTheDocument();
  });

  it('redirects to home automatically after 15 seconds', () => {
    mockSelectedTip = 'نصيحة طبية';
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    expect(mockNavigate).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(15000);
    });

    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('navigates home instantly when Home button is clicked', () => {
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    const homeButton = screen.getByRole('button', { name: /home/i });
    fireEvent.click(homeButton);

    expect(mockNavigate).toHaveBeenCalledWith('/');
  });

  it('resets survey session and redirects to survey selection if active surveys exist', () => {
    mockSurveysList = [{ id: 'survey-1', title: 'استبيان نشط', isActive: true }];
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    const newSurveyButton = screen.getByRole('button', { name: /new_survey/i });
    fireEvent.click(newSurveyButton);

    expect(mockResetSurveySession).toHaveBeenCalledTimes(1);
    expect(mockNavigate).toHaveBeenCalledWith('/survey-selection');
  });

  it('resets survey session and redirects to home if no active surveys exist', () => {
    mockSurveysList = [{ id: 'survey-1', title: 'استبيان غير نشط', isActive: false }];
    render(
      <MemoryRouter>
        <ThankYouPage />
      </MemoryRouter>
    );

    const newSurveyButton = screen.getByRole('button', { name: /new_survey/i });
    fireEvent.click(newSurveyButton);

    expect(mockResetSurveySession).toHaveBeenCalledTimes(1);
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });
});
