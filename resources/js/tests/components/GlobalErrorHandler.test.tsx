import { render, screen, fireEvent, act } from '@testing-library/react';
import { GlobalErrorHandler } from '../../components/GlobalErrorHandler';
import { useErrorStore } from '../../store/useErrorStore';
import { vi, describe, it, expect, beforeEach } from 'vitest';

// Mock Lucide Icons so they don't break testing with large render markups
vi.mock('lucide-react', () => ({
  RefreshCcw: () => <span>RefreshCcw</span>,
  XCircle: () => <span>XCircle</span>,
  WifiOff: () => <span>WifiOff</span>,
  ShieldAlert: () => <span>ShieldAlert</span>,
  ArrowRight: () => <span>ArrowRight</span>,
  Info: () => <span>Info</span>,
}));

const ProblematicComponent = () => {
  throw new Error('Rendering Test Error');
};

describe('GlobalErrorHandler Component', () => {
  beforeEach(() => {
    // Clear Zustand store errors before each test
    useErrorStore.getState().clearAllErrors();
    vi.restoreAllMocks();
  });

  it('renders children normal state without errors', () => {
    render(
      <GlobalErrorHandler>
        <div data-testid="child">Hello World</div>
      </GlobalErrorHandler>
    );

    expect(screen.getByTestId('child')).toBeInTheDocument();
    expect(screen.queryByText('حدث خطأ تقني جسيم')).not.toBeInTheDocument();
  });

  it('catches rendering errors and shows the fatal error UI screen', () => {
    // Suppress console.error inside Vitest for clean outputs during expected boundaries
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    render(
      <GlobalErrorHandler>
        <ProblematicComponent />
      </GlobalErrorHandler>
    );

    expect(screen.getByText('حدث خطأ تقني جسيم')).toBeInTheDocument();
    expect(screen.getByText(/Rendering Test Error/)).toBeInTheDocument();
    
    spy.mockRestore();
  });

  it('subscribes to useErrorStore and renders API error toasts', async () => {
    render(
      <GlobalErrorHandler>
        <div data-testid="child">Normal Content</div>
      </GlobalErrorHandler>
    );

    expect(screen.queryByText('فشل تحميل البيانات')).not.toBeInTheDocument();

    act(() => {
      useErrorStore.getState().addApiError('فشل تحميل البيانات', 500);
    });

    expect(screen.getByText('فشل تحميل البيانات')).toBeInTheDocument();
    expect(screen.getByText('خطأ في الخادم (500)')).toBeInTheDocument();
  });

  it('handles WIFI connection errors correctly displaying appropriate label', () => {
    render(
      <GlobalErrorHandler>
        <div data-testid="child">Normal Content</div>
      </GlobalErrorHandler>
    );

    act(() => {
      useErrorStore.getState().addApiError('انقطع الاتصال بالإنترنت', 0);
    });

    expect(screen.getByText('انقطع الاتصال بالإنترنت')).toBeInTheDocument();
    expect(screen.getByText('خطأ في الاتصال')).toBeInTheDocument();
  });

  it('dismisses API error toasts when dismiss button is clicked', () => {
    render(
      <GlobalErrorHandler>
        <div data-testid="child">Normal Content</div>
      </GlobalErrorHandler>
    );

    act(() => {
      useErrorStore.getState().addApiError('رسالة خطأ مؤقتة', 400);
    });

    expect(screen.getByText('رسالة خطأ مؤقتة')).toBeInTheDocument();

    const dismissButtons = screen.getAllByRole('button');
    // Click on the dismiss button
    fireEvent.click(dismissButtons[1]); // The close icon button

    expect(screen.queryByText('رسالة خطأ مؤقتة')).not.toBeInTheDocument();
    expect(useErrorStore.getState().apiErrors).toHaveLength(0);
  });
});
