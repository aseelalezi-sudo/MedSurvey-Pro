import { render, screen, act } from '@testing-library/react';
import Dashboard from '../../components/Dashboard';
import { vi, describe, it, expect } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

// Mock react-i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (str: string) => str,
    i18n: { language: 'ar', changeLanguage: () => Promise.resolve() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// Mock Recharts
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: any) => <div data-testid="responsive-container">{children}</div>,
  BarChart: () => <div data-testid="bar-chart">BarChart</div>,
  LineChart: () => <div data-testid="line-chart">LineChart</div>,
  PieChart: () => <div data-testid="pie-chart">PieChart</div>,
  RadarChart: () => <div data-testid="radar-chart">RadarChart</div>,
  XAxis: () => null,
  YAxis: () => null,
  Tooltip: () => null,
  Bar: () => null,
  Line: () => null,
  Cell: () => null,
  Pie: () => null,
  CartesianGrid: () => null,
  Legend: () => null,
  PolarGrid: () => null,
  PolarAngleAxis: () => null,
  PolarRadiusAxis: () => null,
  Radar: () => null,
}));

// Mock Stores
vi.mock('../../store/useAuthStore', () => ({
  useAuthStore: () => ({
    currentUser: { id: 'admin-1', name: 'مدير النظام', role: 'admin', username: 'admin' },
  }),
  rolePermissions: {
    admin: { canViewStats: true, canExportData: true },
    super_admin: { canViewStats: true, canExportData: true },
    head_of_department: { canViewStats: true, canExportData: false },
  }
}));

vi.mock('../../store/useThemeStore', () => ({
  useThemeStore: () => ({
    theme: 'light',
  }),
}));

vi.mock('../../store/useSettingsStore', () => ({
  useSettingsStore: () => ({
    settings: { activatedPredictivePlans: [] },
  }),
}));

vi.mock('../../store/useTicketsStore', () => ({
  useTicketsStore: () => ({
    tickets: [],
    loadingTickets: false,
    loadTickets: vi.fn().mockResolvedValue({}),
  }),
}));

vi.mock('../../store/useResponsesStore', () => ({
  useResponsesStore: () => ({
    responses: [],
    stats: {
      totalResponses: 150,
      averageScore: 85,
      npsScore: 45,
      responseRate: 92,
      satisfactionDistribution: [],
      departmentScores: [],
      trendData: [],
      hourlyStats: [],
      dayStats: [],
      categoryScores: []
    },
    loading: false,
    loadDashboardData: vi.fn().mockResolvedValue({}),
  }),
}));

describe('Dashboard Component', () => {
  it('renders dashboard title and main stats', async () => {
    await act(async () => {
      render(
        <MemoryRouter>
          <Dashboard />
        </MemoryRouter>
      );
    });
    
    // Use getAllByText for values that might appear multiple times (main stat + change indicator)
    expect(screen.getByText('ai_dashboard_status')).toBeInTheDocument();
    expect(screen.getAllByText('150')).toHaveLength(1); 
    expect(screen.getAllByText('85%').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('92%')).toBeInTheDocument();
  });
});
