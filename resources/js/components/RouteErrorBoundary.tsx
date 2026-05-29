import { Component, ErrorInfo, ReactNode } from 'react';
import { AlertTriangle, RotateCcw } from 'lucide-react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

export class RouteErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Route level boundary caught error:', error, errorInfo);
  }

  private handleRetry = () => {
    // If it's a chunk/module loading failure, reload the page from server
    const errorMsg = this.state.error?.message || '';
    const errorName = this.state.error?.name || '';
    if (
      errorName === 'ChunkLoadError' ||
      errorMsg.includes('Failed to fetch dynamically imported module') ||
      errorMsg.includes('importing')
    ) {
      window.location.reload();
    } else {
      this.setState({ hasError: false, error: null });
    }
  };

  public render() {
    if (this.state.hasError) {
      const isRtl = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
      const title = isRtl ? 'عذراً، فشل تحميل الصفحة' : 'Oops, failed to load page';
      const desc = isRtl
        ? 'واجه المتصفح صعوبة في معالجة أو عرض محتويات هذه الصفحة. قد يكون ذلك بسبب تحديث للنظام أو انقطاع مؤقت في الشبكة.'
        : 'The browser encountered an error loading or rendering this page. This could be due to a recent system update or a temporary connection issue.';
      const retryBtn = isRtl ? 'إعادة المحاولة' : 'Try Again';

      return (
        <div className="flex flex-col items-center justify-center p-8 text-center min-h-[350px] bg-slate-50 dark:bg-slate-900/40 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800 animate-scale-in m-4">
          <div className="w-16 h-16 bg-amber-50 dark:bg-amber-500/10 rounded-2xl flex items-center justify-center text-amber-500 mb-5 relative">
            <div className="absolute inset-0 bg-amber-500/10 rounded-2xl animate-pulse" />
            <AlertTriangle className="w-8 h-8" />
          </div>
          <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-2">
            {title}
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400 mb-6 max-w-md leading-relaxed">
            {desc}
          </p>
          <button
            onClick={this.handleRetry}
            className="flex items-center gap-2 bg-teal-600 hover:bg-teal-700 dark:bg-teal-500 dark:hover:bg-teal-600 text-white px-6 py-3 rounded-2xl font-bold transition-all cursor-pointer shadow-lg active:scale-95 text-sm"
          >
            <RotateCcw className="w-4 h-4" />
            {retryBtn}
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
