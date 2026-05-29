import { useEffect, lazy, Suspense, ComponentType } from 'react';
import type { ReactElement } from 'react';
import type { UserPermission } from './types/auth';
import { HashRouter, Routes, Route, useLocation, Navigate } from 'react-router-dom';
import { useAuthStore } from './store/useAuthStore';
import { useSettingsStore } from './store/useSettingsStore';
import { useSurveyStore } from './store/useSurveyStore';
import * as Sentry from '@sentry/react';
import { useTranslation } from 'react-i18next';

// Fault-tolerant lazy loading wrapper to retry chunk loads on deploy updates
// eslint-disable-next-line @typescript-eslint/no-explicit-any
function lazyWithRetry<T extends ComponentType<any>>(componentImport: () => Promise<{ default: T }>) {
  return lazy(async () => {
    const pageHasAlreadyRetried = window.sessionStorage.getItem('page-has-retried');
    try {
      const result = await componentImport();
      window.sessionStorage.removeItem('page-has-retried');
      return result;
    } catch (error) {
      if (!pageHasAlreadyRetried) {
        window.sessionStorage.setItem('page-has-retried', 'true');
        window.location.reload();
        return new Promise(() => {}); // Hang while reloading
      }
      throw error;
    }
  });
}

// Lazy Loaded Components
const LandingPage = lazyWithRetry(() => import('./components/LandingPage'));
const LoginPage = lazyWithRetry(() => import('./components/LoginPage'));
const PatientInfoForm = lazyWithRetry(() => import('./components/PatientInfoForm'));
const SurveyPage = lazyWithRetry(() => import('./components/SurveyPage'));
const ThankYouPage = lazyWithRetry(() => import('./components/ThankYouPage'));
const Dashboard = lazyWithRetry(() => import('./components/Dashboard'));
const SettingsPage = lazyWithRetry(() => import('./components/SettingsPage'));
const ResponsesPage = lazyWithRetry(() => import('./components/ResponsesPage'));
const SurveyBuilder = lazyWithRetry(() => import('./components/SurveyBuilder'));
const SurveySelection = lazyWithRetry(() => import('./components/SurveySelection'));
const UserManagement = lazyWithRetry(() => import('./components/UserManagement'));
const AuditLogsPage = lazyWithRetry(() => import('./components/AuditLogsPage'));
const DashboardLayout = lazyWithRetry(() => import('./components/DashboardLayout'));
const TicketsPage = lazyWithRetry(() => import('./components/TicketsPage'));
const PredictivePage = lazyWithRetry(() => import('./components/PredictivePage'));
const HallOfFamePage = lazyWithRetry(() => import('./components/HallOfFamePage'));
const ReportsPage = lazyWithRetry(() => import('./components/ReportsPage'));
const MonitoringDashboard = lazyWithRetry(() => import('./components/MonitoringDashboard'));
const ErrorLogsPage = lazyWithRetry(() => import('./components/ErrorLogsPage'));
const BackupsPage = lazyWithRetry(() => import('./components/BackupsPage'));

if (import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [Sentry.browserTracingIntegration(), Sentry.replayIntegration()],
    tracesSampleRate: import.meta.env.DEV ? 1.0 : 0.1,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
  });
}

function PageLoader({ message }: { message?: string }) {
  const { t, i18n } = useTranslation();
  return (
    <div
      className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center p-4 relative overflow-hidden"
      dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}
    >
      {/* Ambient background glows */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow" />
      <div
        className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow"
        style={{ animationDelay: '1.5s' }}
      />

      <div className="relative bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-100 dark:border-slate-800 p-8 sm:p-12 rounded-3xl shadow-2xl max-w-sm w-full text-center flex flex-col items-center gap-6 animate-scale-in">
        {/* Pulsing ring spinner */}
        <div className="relative w-24 h-24 flex items-center justify-center">
          <div className="absolute inset-0 rounded-full border-4 border-teal-500/10 animate-ping" />
          <div className="absolute inset-2 rounded-full border-4 border-emerald-500/20 animate-pulse-soft" />
          <div className="w-16 h-16 rounded-2xl bg-linear-to-r from-teal-500 to-emerald-500 flex items-center justify-center shadow-lg shadow-teal-500/20 animate-bounce-soft">
            <svg className="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2.5"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18"
              />
            </svg>
          </div>
        </div>

        <div className="space-y-2">
          <h1 className="text-xl sm:text-2xl font-black text-slate-800 dark:text-white tracking-tight">
            MedSurvey Pro
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-extrabold">
            {message || t('app_loading_message', 'جاري التحميل...')}
          </p>
        </div>

        {/* Custom micro-progress bar */}
        <div className="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden relative">
          <div
            className="absolute top-0 bottom-0 left-0 bg-linear-to-r from-teal-500 to-emerald-500 rounded-full animate-loading-bar"
            style={{ width: '50%' }}
          />
        </div>
      </div>
    </div>
  );
}

function AppContent() {
  const { t, i18n } = useTranslation();
  const location = useLocation();
  const { currentUser, hasPermission } = useAuthStore();
  const { settings } = useSettingsStore();
  const { loadingSurveys, loadSurveys } = useSurveyStore();

  // Sync HTML dir and lang attributes with current i18n language
  useEffect(() => {
    const dir = i18n.language === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.dir = dir;
    document.documentElement.lang = i18n.language;
  }, [i18n.language]);

  // Scroll to top on route change
  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
  }, [location.pathname, location.search]);

  // Apply appearance colors dynamically
  useEffect(() => {
    if (settings?.appearance) {
      const { primaryColor, secondaryColor } = settings.appearance;
      if (primaryColor) {
        document.documentElement.style.setProperty('--primary-color', primaryColor);
      }
      if (secondaryColor) {
        document.documentElement.style.setProperty('--secondary-color', secondaryColor);
      }
    }
  }, [settings?.appearance]);

  // Load surveys on app start
  useEffect(() => {
    loadSurveys();
  }, [loadSurveys]);

  // Dynamic SEO based on location
  useEffect(() => {
    let title = t('app_title_default', 'MedSurvey Pro - نظام استبيانات رضا المرضى');
    let description = t(
      'app_desc_default',
      'نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية.',
    );
    const path = location.pathname;

    if (path === '/') {
      title = t('app_title_home', 'الصفحة الرئيسية | MedSurvey Pro');
      description = t(
        'app_desc_home',
        'مرحباً بك في نظام قياس رضا المرضى. شاركنا رأيك لنسعى دائماً نحو التميز في الرعاية الصحية.',
      );
    } else if (path.includes('/survey')) {
      title = t('app_title_survey', 'تقديم استبيان | MedSurvey Pro');
      description = t('app_desc_survey', 'شاركنا رأيك وساهم في تحسين جودة الخدمات الطبية.');
      if (path === '/survey/thanks') {
        title = t('app_title_thank_you', 'شكراً لك | MedSurvey Pro');
        description = t('app_desc_thank_you', 'شكراً لمشاركتك وقتك الثمين معنا.');
      }
    } else if (path === '/login') {
      title = t('app_title_login', 'تسجيل الدخول | إدارة النظام');
    } else if (path.startsWith('/dashboard')) {
      title = t('app_title_dashboard', 'لوحة التحكم | MedSurvey Pro');
      if (path.includes('/responses')) title = t('app_title_responses', 'إدارة الاستجابات | MedSurvey Pro');
      if (path.includes('/tickets')) title = t('app_title_tickets', 'تذاكر المتابعة الفورية | MedSurvey Pro');
      if (path.includes('/surveys')) title = t('app_title_surveys', 'إدارة الاستبيانات | MedSurvey Pro');
      if (path.includes('/users')) title = t('app_title_users', 'إدارة الصلاحيات والمستخدمين | MedSurvey Pro');
      if (path.includes('/settings')) title = t('app_title_settings', 'إعدادات النظام | MedSurvey Pro');
      if (path.includes('/hall-of-fame')) title = t('app_title_hall_of_fame', 'لوحة الشرف | MedSurvey Pro');
    }

    document.title = title;

    // Helper to update or create meta tags
    const updateMetaTag = (selector: string, value: string) => {
      let element = document.querySelector(selector);
      if (element) {
        element.setAttribute('content', value);
      } else {
        element = document.createElement('meta');
        if (selector.startsWith('meta[')) {
          const matches = selector.match(/meta\[(.*?)="(.*?)"\]/);
          if (matches && matches.length === 3) {
            element.setAttribute(matches[1], matches[2]);
          }
        }
        element.setAttribute('content', value);
        document.head.appendChild(element);
      }
    };

    updateMetaTag('meta[name="description"]', description);
    updateMetaTag('meta[property="og:title"]', title);
    updateMetaTag('meta[property="og:description"]', description);
    updateMetaTag('meta[property="twitter:title"]', title);
    updateMetaTag('meta[property="twitter:description"]', description);
  }, [location.pathname, t]);

  // Loading screen
  if (loadingSurveys) {
    return <PageLoader message={t('app_loading_system', 'جاري تهيئة النظام وتحميل الاستبيانات الذكية...')} />;
  }

  const requirePermission = (permission: keyof UserPermission, element: ReactElement) => {
    if (!currentUser) return <Navigate to="/login" />;
    return hasPermission(permission) ? element : <Navigate to="/dashboard" replace />;
  };

  const requireAnyPermission = (permissions: (keyof UserPermission)[], element: ReactElement) => {
    if (!currentUser) return <Navigate to="/login" />;
    return permissions.some((permission) => hasPermission(permission)) ? element : <Navigate to="/dashboard" replace />;
  };

  return (
    <div className="font-cairo min-w-0 overflow-x-hidden" dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>
      <Suspense fallback={<PageLoader message={t('app_loading_message', 'جاري التحميل...')} />}>
        <Routes>
          {/* Public Routes */}
          <Route path="/" element={<LandingPage />} />
          <Route
            path="/login"
            element={
              currentUser ? (
                <Navigate to={currentUser.role === 'staff' ? '/dashboard/responses' : '/dashboard'} />
              ) : (
                <LoginPage />
              )
            }
          />

          <Route path="/survey-selection" element={<SurveySelection />} />
          <Route path="/survey/info" element={<PatientInfoForm />} />
          <Route path="/survey/take" element={<SurveyPage />} />
          <Route path="/survey/thanks" element={<ThankYouPage />} />

          {/* Admin Dashboard Routes */}
          <Route path="/dashboard" element={currentUser ? <DashboardLayout /> : <Navigate to="/login" />}>
            <Route index element={<Dashboard />} />
            <Route
              path="responses"
              element={requireAnyPermission(
                ['canViewAllReports', 'canViewDepartmentReports', 'canViewResponses'],
                <ResponsesPage />,
              )}
            />
            <Route
              path="reports"
              element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <ReportsPage />)}
            />
            <Route
              path="predictive"
              element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <PredictivePage />)}
            />
            <Route
              path="tickets"
              element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <TicketsPage />)}
            />
            <Route path="surveys" element={requirePermission('canManageSurveys', <SurveyBuilder />)} />
            <Route path="users" element={requirePermission('canManageUsers', <UserManagement />)} />
            <Route path="audit" element={requirePermission('canManageUsers', <AuditLogsPage />)} />
            <Route path="settings" element={requirePermission('canManageUsers', <SettingsPage />)} />
            <Route path="monitoring" element={requirePermission('canManageUsers', <MonitoringDashboard />)} />
            <Route path="error-logs" element={requirePermission('canManageUsers', <ErrorLogsPage />)} />
            <Route path="backups" element={requirePermission('canManageUsers', <BackupsPage />)} />
            <Route path="hall-of-fame" element={<HallOfFamePage />} />
          </Route>

          {/* Catch all */}
          <Route path="*" element={<Navigate to="/" />} />
        </Routes>
      </Suspense>
    </div>
  );
}

export default function App() {
  return (
    <HashRouter>
      <AppContent />
    </HashRouter>
  );
}
