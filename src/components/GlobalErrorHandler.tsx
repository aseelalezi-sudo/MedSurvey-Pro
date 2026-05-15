import { Component, ErrorInfo, ReactNode } from 'react';
import { 
  RefreshCcw, 
  XCircle, 
  WifiOff, 
  ShieldAlert,
  ArrowRight,
  Info
} from 'lucide-react';
import { createLogger } from '../utils/logger';

const logger = createLogger('GlobalErrorHandler');

interface Props {
  children: ReactNode;
}

interface ApiErrorDetail {
  message: string;
  status: number;
}

interface State {
  hasFatalError: boolean;
  fatalError: Error | null;
  apiErrors: { id: string; message: string; status: number }[];
}

/**
 * GlobalErrorHandler — A centralized mission-control for all system errors.
 * 1. React Error Boundary: Catches runtime rendering failures.
 * 2. Unhandled Rejections: Catches async/Promise failures.
 * 3. Window Errors: Catches other browser-level exceptions.
 * 4. API Error Events: Centralized toast notifications for MedSurvey API failures.
 */
export class GlobalErrorHandler extends Component<Props, State> {
  public state: State = {
    hasFatalError: false,
    fatalError: null,
    apiErrors: []
  };

  private errorCount = 0;

  public static getDerivedStateFromError(error: Error): Partial<State> {
    return { hasFatalError: true, fatalError: error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    logger.error('CRITICAL: Rendering Error Detected', { error, errorInfo });
    // In a real app, send this to Sentry or similar logging service
  }

  public componentDidMount() {
    // 1. Catch Unhandled Promise Rejections (API calls without .catch, etc.)
    window.addEventListener('unhandledrejection', this.handlePromiseRejection);
    
    // 2. Catch Global Window Errors
    window.addEventListener('error', this.handleWindowError);

    // 3. Listen for Custom API Error Events dispatched by client.ts
    window.addEventListener('medsurvey-api-error', this.handleApiError as EventListener);
  }

  public componentWillUnmount() {
    window.removeEventListener('unhandledrejection', this.handlePromiseRejection);
    window.removeEventListener('error', this.handleWindowError);
    window.removeEventListener('medsurvey-api-error', this.handleApiError as EventListener);
  }

  private handlePromiseRejection = (event: PromiseRejectionEvent) => {
    logger.error('Unhandled Promise Rejection:', event.reason);
    this.addApiError(event.reason?.message || 'فشل الاتصال بالخادم بشكل غير متوقع', 0);
  };

  private handleWindowError = (event: ErrorEvent) => {
    logger.error('Global Window Error:', event.error);
    // Only make it fatal if it's not handled elsewhere
    if (!this.state.hasFatalError) {
      this.setState({ hasFatalError: true, fatalError: event.error });
    }
  };

  private handleApiError = (event: CustomEvent<ApiErrorDetail>) => {
    const { message, status } = event.detail;
    this.addApiError(message, status);
  };

  private addApiError = (message: string, status: number) => {
    const id = `error-${++this.errorCount}`;
    
    // Check if error is already present (prevent spamming same error)
    if (this.state.apiErrors.some(e => e.message === message)) return;

    this.setState(prev => ({
      apiErrors: [...prev.apiErrors, { id, message, status }]
    }));

    // Auto-dismiss after 6 seconds
    setTimeout(() => {
      this.dismissApiError(id);
    }, 6000);
  };

  private dismissApiError = (id: string) => {
    this.setState(prev => ({
      apiErrors: prev.apiErrors.filter(e => e.id !== id)
    }));
  };

  private resetFatalError = () => {
    this.setState({ hasFatalError: false, fatalError: null });
    window.location.href = '/'; // Reset to safe home
  };

  public render() {
    const { hasFatalError, fatalError, apiErrors } = this.state;

    // --- FATAL ERROR UI (The "Oops" Page) ---
    if (hasFatalError) {
      return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-4 sm:p-6" dir="rtl">
          <div className="absolute inset-0 bg-grid-slate-100 [mask-image:linear-gradient(0deg,#fff,rgba(255,255,255,0.6))] dark:bg-grid-slate-700/20 dark:[mask-image:linear-gradient(0deg,rgba(0,0,0,0.1),rgba(0,0,0,0.5))] pointer-events-none" />
          
          <div className="bg-white dark:bg-slate-900 rounded-[2.5rem] p-8 sm:p-12 max-w-lg w-full shadow-2xl dark:shadow-indigo-500/10 border border-slate-100 dark:border-slate-800 text-center relative overflow-hidden animate-scale-in">
            {/* Background Accent */}
            <div className="absolute top-0 right-0 w-32 h-32 bg-red-500/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
            
            <div className="w-24 h-24 bg-red-50 dark:bg-red-500/10 rounded-3xl flex items-center justify-center mx-auto mb-8 relative">
              <div className="absolute inset-0 bg-red-500/20 rounded-3xl animate-ping opacity-20" />
              <ShieldAlert className="w-12 h-12 text-red-500" />
            </div>

            <h1 className="text-3xl font-black text-slate-900 dark:text-white mb-4 tracking-tight">
              حدث خطأ تقني جسيم
            </h1>
            
            <p className="text-slate-500 dark:text-slate-400 mb-10 text-sm leading-relaxed max-w-sm mx-auto">
              عذراً، واجه نظام MedSurvey Pro مشكلة غير متوقعة تمنع استمرار العرض. تم تسجيل تفاصيل الخطأ للمراجعة التقنية.
            </p>
            
            {fatalError && (
              <div className="bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 p-5 rounded-2xl text-left mb-10 overflow-auto max-h-40 group" dir="ltr">
                <div className="flex items-center gap-2 mb-2 text-slate-400 dark:text-slate-500">
                  <Info className="w-3 h-3" />
                  <span className="text-[10px] font-bold uppercase tracking-widest">Stack Trace</span>
                </div>
                <code className="text-[11px] text-red-500 dark:text-red-400 font-mono break-all leading-tight opacity-80 group-hover:opacity-100 transition-opacity">
                  {fatalError.name}: {fatalError.message}
                </code>
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <button
                onClick={() => window.location.reload()}
                className="flex items-center justify-center gap-2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-4 rounded-2xl font-bold hover:opacity-90 transition-all cursor-pointer shadow-lg active:scale-95"
              >
                <RefreshCcw className="w-5 h-5" />
                تحديث الصفحة
              </button>
              
              <button
                onClick={this.resetFatalError}
                className="flex items-center justify-center gap-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-white px-6 py-4 rounded-2xl font-bold hover:bg-slate-200 dark:hover:bg-slate-750 transition-all cursor-pointer"
              >
                العودة للرئيسية
                <ArrowRight className="w-5 h-5 mr-1" />
              </button>
            </div>
            
            <p className="mt-8 text-[10px] text-slate-400 dark:text-slate-600 font-medium uppercase tracking-[0.2em]">
              MedSurvey Pro • Advanced Error Protection
            </p>
          </div>
        </div>
      );
    }

    // --- NORMAL RENDER WITH TOASTS ---
    return (
      <>
        {this.props.children}

        {/* Global Toast Overlay for API Errors */}
        <div className="fixed bottom-6 left-6 right-6 sm:left-auto sm:right-6 sm:w-[400px] z-[9999] flex flex-col gap-3 pointer-events-none" dir="rtl">
          {apiErrors.map((error) => (
            <div 
              key={error.id}
              className="bg-white dark:bg-slate-900 border-l-4 border-l-red-500 rounded-2xl p-4 shadow-2xl dark:shadow-red-500/10 animate-slide-up flex gap-3 items-start pointer-events-auto group relative overflow-hidden"
            >
              <div className="absolute inset-0 bg-red-500/5 opacity-0 group-hover:opacity-100 transition-opacity" />
              
              <div className="w-10 h-10 bg-red-50 dark:bg-red-500/10 rounded-xl flex items-center justify-center text-red-500 flex-shrink-0">
                {error.status === 0 ? <WifiOff className="w-5 h-5" /> : <XCircle className="w-5 h-5" />}
              </div>
              
              <div className="flex-1 text-start pt-0.5">
                <div className="flex items-center justify-between mb-1">
                  <span className="text-[10px] font-black text-red-500 uppercase tracking-widest">
                    {error.status === 0 ? 'خطأ في الاتصال' : `خطأ في الخادم (${error.status})`}
                  </span>
                  <button 
                    onClick={() => this.dismissApiError(error.id)}
                    className="text-slate-400 hover:text-slate-600 dark:hover:text-white transition-colors cursor-pointer"
                  >
                    <RefreshCcw className="w-3.5 h-3.5" />
                  </button>
                </div>
                <p className="text-sm font-bold text-slate-800 dark:text-slate-200 leading-tight">
                  {error.message}
                </p>
              </div>
              
              {/* Dismiss button */}
              <button 
                onClick={() => this.dismissApiError(error.id)}
                className="absolute top-2 left-2 p-1 text-slate-300 dark:text-slate-700 hover:text-red-500 transition-colors cursor-pointer"
              >
                <XCircle className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
      </>
    );
  }
}
