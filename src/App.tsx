import { ReactElement, useEffect, useState } from 'react';
import { HashRouter, Routes, Route, useLocation, Navigate } from 'react-router-dom';
import { useAuthStore } from './store/useAuthStore';
import { useSettingsStore } from './store/useSettingsStore';
import { useSurveyStore } from './store/useSurveyStore';

// Components
import LandingPage from './components/LandingPage';
import LoginPage from './components/LoginPage';
import PatientInfoForm from './components/PatientInfoForm';
import SurveyPage from './components/SurveyPage';
import ThankYouPage from './components/ThankYouPage';
import Dashboard from './components/Dashboard';
import SettingsPage from './components/SettingsPage';
import ResponsesPage from './components/ResponsesPage';
import SurveyBuilder from './components/SurveyBuilder';
import SurveySelection from './components/SurveySelection';
import UserManagement from './components/UserManagement';
import AuditLogsPage from './components/AuditLogsPage';
import DashboardLayout from './components/DashboardLayout';
import TicketsPage from './components/TicketsPage';
import PredictivePage from './components/PredictivePage';
import HallOfFamePage from './components/HallOfFamePage';
import ReportsPage from './components/ReportsPage';
import MonitoringDashboard from './components/MonitoringDashboard';
import * as Sentry from "@sentry/react";
import { UserPermission } from './store/useAuthStore';

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

function AppContent() {
  const location = useLocation();
  const { currentUser, hasPermission } = useAuthStore();
  const { settings } = useSettingsStore();
  const { loadingSurveys, loadSurveys } = useSurveyStore();

  // Global premium toast state for unified API error presentation
  const [globalToast, setGlobalToast] = useState<{ show: boolean; message: string }>({
    show: false,
    message: '',
  });

  // Scroll to top on route change
  useEffect(() => {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
  }, [location.pathname, location.search]);

  // Global API error handler
  useEffect(() => {
    let timer: NodeJS.Timeout;
    const handleApiError = (e: Event) => {
      const customEvent = e as CustomEvent<{ message: string; status: number }>;
      const { message } = customEvent.detail;
      setGlobalToast({ show: true, message });

      clearTimeout(timer);
      timer = setTimeout(() => {
        setGlobalToast(prev => ({ ...prev, show: false }));
      }, 6000);
    };

    window.addEventListener('medsurvey-api-error', handleApiError);
    return () => {
      window.removeEventListener('medsurvey-api-error', handleApiError);
      clearTimeout(timer);
    };
  }, []);

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
    return (
      <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col items-center justify-center p-4 relative overflow-hidden" dir="rtl">
        {/* Ambient background glows */}
        <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow" />
        <div className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none animate-pulse-slow" style={{ animationDelay: '1.5s' }} />

        <div className="relative bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-slate-100 dark:border-slate-800 p-8 sm:p-12 rounded-3xl shadow-2xl max-w-sm w-full text-center flex flex-col items-center gap-6 animate-scale-in">
          {/* Pulsing ring spinner */}
          <div className="relative w-24 h-24 flex items-center justify-center">
            <div className="absolute inset-0 rounded-full border-4 border-teal-500/10 animate-ping" />
            <div className="absolute inset-2 rounded-full border-4 border-emerald-500/20 animate-pulse-soft" />
            <div className="w-16 h-16 rounded-2xl bg-gradient-to-tr from-teal-500 to-emerald-500 flex items-center justify-center shadow-lg shadow-teal-500/20 animate-bounce-soft">
              <svg className="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18" />
              </svg>
            </div>
          </div>

          <div className="space-y-2">
            <h1 className="text-xl sm:text-2xl font-black text-slate-800 dark:text-white tracking-tight">MedSurvey Pro</h1>
            <p className="text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-extrabold">جاري تهيئة النظام وتحميل الاستبيانات الذكية...</p>
          </div>

          {/* Custom micro-progress bar */}
          <div className="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden relative">
            <div className="absolute top-0 bottom-0 left-0 bg-gradient-to-r from-teal-500 to-emerald-500 rounded-full animate-loading-bar" style={{ width: '50%' }} />
          </div>
        </div>
      </div>
    );
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
    <div className="font-cairo min-w-0 overflow-x-hidden" dir="rtl">
      <Routes>
        {/* Public Routes */}
        <Route path="/" element={<LandingPage />} />
        <Route path="/login" element={currentUser ? <Navigate to="/dashboard" /> : <LoginPage />} />

        <Route path="/survey-selection" element={<SurveySelection />} />
        <Route path="/survey/info" element={<PatientInfoForm />} />
        <Route path="/survey/take" element={<SurveyPage />} />
        <Route path="/survey/thanks" element={<ThankYouPage />} />

        {/* Admin Dashboard Routes */}
        <Route path="/dashboard" element={currentUser ? <DashboardLayout /> : <Navigate to="/login" />}>
          <Route index element={<Dashboard />} />
          <Route path="responses" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <ResponsesPage />)} />
          <Route path="reports" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <ReportsPage />)} />
          <Route path="predictive" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <PredictivePage />)} />
          <Route path="tickets" element={requireAnyPermission(['canViewAllReports', 'canViewDepartmentReports'], <TicketsPage />)} />
          <Route path="surveys" element={requirePermission('canManageSurveys', <SurveyBuilder />)} />
          <Route path="users" element={requirePermission('canManageUsers', <UserManagement />)} />
          <Route path="audit" element={requirePermission('canManageUsers', <AuditLogsPage />)} />
          <Route path="settings" element={requirePermission('canManageUsers', <SettingsPage />)} />
          <Route path="monitoring" element={requirePermission('canManageUsers', <MonitoringDashboard />)} />
          <Route path="hall-of-fame" element={<HallOfFamePage />} />
        </Route>

        {/* Catch all */}
        <Route path="*" element={<Navigate to="/" />} />
      </Routes>

      {/* Premium Global API Error Glassmorphism Toast Alert */}
      {globalToast.show && (
        <div className="fixed bottom-4 right-4 left-4 sm:left-auto sm:bottom-6 sm:right-6 z-[9999] flex items-center gap-3 sm:gap-4 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-red-200 dark:border-red-900/50 p-4 rounded-2xl shadow-2xl animate-slide-up sm:max-w-sm transition-all duration-300">
          <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-red-100 dark:bg-red-950/50 flex items-center justify-center">
            <svg className="w-6 h-6 text-red-600 dark:text-red-400 animate-pulse-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.1" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div className="flex-1 min-w-0">
            <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">حدث خطأ في النظام</h4>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 font-semibold truncate-3-lines">{globalToast.message}</p>
          </div>
          <button
            onClick={() => setGlobalToast(prev => ({ ...prev, show: false }))}
            className="flex-shrink-0 w-6 h-6 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-all duration-200"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      )}
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
