import { configureEcho } from '@laravel/echo-react';

configureEcho({
    broadcaster: 'reverb',
});
import { ReactElement, useEffect, lazy, Suspense } from 'react';
import { HashRouter, Routes, Route, useLocation, Navigate } from 'react-router-dom';
import { useAuthStore, UserPermission } from './store/useAuthStore';
import { useSettingsStore } from './store/useSettingsStore';
import { useSurveyStore } from './store/useSurveyStore';
import * as Sentry from "@sentry/react";
import { useTranslation } from 'react-i18next';

// Lazy Loaded Components
const LandingPage = lazy(() => import('./components/LandingPage'));
const LoginPage = lazy(() => import('./components/LoginPage'));
const PatientInfoForm = lazy(() => import('./components/PatientInfoForm'));
const SurveyPage = lazy(() => import('./components/SurveyPage'));
const ThankYouPage = lazy(() => import('./components/ThankYouPage'));
const Dashboard = lazy(() => import('./components/Dashboard'));
const SettingsPage = lazy(() => import('./components/SettingsPage'));
const ResponsesPage = lazy(() => import('./components/ResponsesPage'));
const SurveyBuilder = lazy(() => import('./components/SurveyBuilder'));
const SurveySelection = lazy(() => import('./components/SurveySelection'));
const UserManagement = lazy(() => import('./components/UserManagement'));
const AuditLogsPage = lazy(() => import('./components/AuditLogsPage'));
const DashboardLayout = lazy(() => import('./components/DashboardLayout'));
const TicketsPage = lazy(() => import('./components/TicketsPage'));
const PredictivePage = lazy(() => import('./components/PredictivePage'));
const HallOfFamePage = lazy(() => import('./components/HallOfFamePage'));
const ReportsPage = lazy(() => import('./components/ReportsPage'));
const MonitoringDashboard = lazy(() => import('./components/MonitoringDashboard'));
const ErrorLogsPage = lazy(() => import('./components/ErrorLogsPage'));
const BackupsPage = lazy(() => import('./components/BackupsPage'));

if (import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [
      Sentry.browserTracingIntegration(),
      Sentry.replayIntegration(),
    ],
    tracesSampleRate: 1.0,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
  });
}

function PageLoader({ message = 'جاري التحميل...' }: { message?: string }) {
  const { i18n } = useTranslation();
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center p-4 relative overflow-hidden" dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>
      {/* Ambient background glows */}
      <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow" />
      <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow" style={{ animationDelay: '1.5s' }} />

      <div className="relative bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-100 dark:border-slate-800 p-8 sm:p-12 rounded-3xl shadow-2xl max-w-sm w-full text-center flex flex-col items-center gap-6 animate-scale-in">
        {/* Pulsing ring spinner */}
        <div className="relative w-24 h-24 flex items-center justify-center">
          <div className="absolute inset-0 rounded-full border-4 border-teal-500/10 animate-ping" />
          <div className="absolute inset-2 rounded-full border-4 border-emerald-500/20 animate-pulse-soft" />
          <div className="w-16 h-16 rounded-2xl bg-linear-to-r from-teal-500 to-emerald-500 flex items-center justify-center shadow-lg shadow-teal-500/20 animate-bounce-soft">
            <svg className="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18" />
            </svg>
          </div>
        </div>

        <div className="space-y-2">
          <h1 className="text-xl sm:text-2xl font-black text-slate-800 dark:text-white tracking-tight">MedSurvey Pro</h1>
          <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-extrabold">{message}</p>
        </div>

        {/* Custom micro-progress bar */}
        <div className="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden relative">
          <div className="absolute top-0 bottom-0 left-0 bg-linear-to-r from-teal-500 to-emerald-500 rounded-full animate-loading-bar" style={{ width: '50%' }} />
        </div>
      </div>
    </div>
  );
}

function AppContent() {
  const { i18n } = useTranslation();
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
    let title = 'MedSurvey Pro - نظام استبيانات رضا المرضى';
    let description = 'نظام متكامل لجمع وتحليل استبيانات رضا المرضى بطريقة ذكية وسرية تضمن تحسين جودة الرعاية الصحية.';
    const path = location.pathname;

    if (path === '/') {
      title = 'الصفحة الرئيسية | MedSurvey Pro';
      description = 'مرحباً بك في نظام قياس رضا المرضى. شاركنا رأيك لنسعى دائماً نحو التميز في الرعاية الصحية.';
    } else if (path.includes('/survey')) {
      title = 'تقديم استبيان | MedSurvey Pro';
      description = 'شاركنا رأيك وساهم في تحسين جودة الخدمات الطبية.';
      if (path === '/survey/thanks') {
        title = 'شكراً لك | MedSurvey Pro';
        description = 'شكراً لمشاركتك وقتك الثمين معنا.';
      }
    } else if (path === '/login') {
      title = 'تسجيل الدخول | إدارة النظام';
    } else if (path.startsWith('/dashboard')) {
      title = 'لوحة التحكم | MedSurvey Pro';
      if (path.includes('/responses')) title = 'إدارة الاستجابات | MedSurvey Pro';
      if (path.includes('/tickets')) title = 'تذاكر المتابعة الفورية | MedSurvey Pro';
      if (path.includes('/surveys')) title = 'إدارة الاستبيانات | MedSurvey Pro';
      if (path.includes('/users')) title = 'إدارة الصلاحيات والمستخدمين | MedSurvey Pro';
      if (path.includes('/settings')) title = 'إعدادات النظام | MedSurvey Pro';
      if (path.includes('/hall-of-fame')) title = 'لوحة الشرف | MedSurvey Pro';
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
  }, [location.pathname]);

  // Loading screen
  if (loadingSurveys) {
    return <PageLoader message="جاري تهيئة النظام وتحميل الاستبيانات الذكية..." />;
  }

  const requirePermission = (permission: keyof UserPermission, element: ReactElement) => {
    if (!currentUser) return <Navigate to="/login" />;
    return hasPermission(permission) ? element : <Navigate to="/dashboard" replace />;
  };

  const requireAnyPermission = (permissions: (keyof UserPermission)[], element: ReactElement) => {
    if (!currentUser) return <Navigate to="/login" />;
    return permissions.some(permission => hasPermission(permission)) ? element : <Navigate to="/dashboard" replace />;
  };

  return (
    <div className="font-cairo min-w-0 overflow-x-hidden" dir={i18n.language === 'ar' ? 'rtl' : 'ltr'}>
      <Suspense fallback={<PageLoader message="جاري التحميل..." />}>
        <Routes>
          {/* Public Routes */}
          <Route path="/" element={<LandingPage />} />
          <Route path="/login" element={currentUser ? <Navigate to={currentUser.role === 'staff' ? '/dashboard/responses' : '/dashboard'} /> : <LoginPage />} />

          <Route path="/survey-selection" element={<SurveySelection />} />
          <Route path="/survey/info" element={<PatientInfoForm />} />
          <Route path="/survey/take" element={<SurveyPage />} />
          <Route path="/survey/thanks" element={<ThankYouPage />} />

          {/* Admin Dashboard Routes */}
          <Route path="/dashboard" element={currentUser ? <DashboardLayout /> : <Navigate to="/login" />}>
            <Route index element={<Dashboard />} />
            <Route path="responses" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports', 'canViewResponses'], <ResponsesPage />)} />
            <Route path="reports" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <ReportsPage />)} />
            <Route path="predictive" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <PredictivePage />)} />
            <Route path="tickets" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <TicketsPage />)} />
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
