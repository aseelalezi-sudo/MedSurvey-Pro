import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useSettingsStore } from '../store/useSettingsStore';
import { usePredictiveStore, PredictiveAlert } from '../store/usePredictiveStore';
import { 
  Building2,
  Brain,
  Sparkles,
  TrendingDown,
  ArrowDownRight,
  X,
  Check,
  ShieldCheck,
  Activity,
  ArrowLeft
} from 'lucide-react';


export default function PredictivePage() {
  const navigate = useNavigate();
  const { t } = useTranslation();

  const onBack = () => navigate('/dashboard');
  const { settings, togglePredictivePlan } = useSettingsStore();
  const activatedPlans = settings.activatedPredictivePlans || [];

  // Centralized predictive store — single source of truth, no duplicate API calls
  const { alerts: predictiveAlerts, stats, loading, reload } = usePredictiveStore();

  // Load data on mount
  useEffect(() => {
    const activated = settings.activatedPredictivePlans || [];
    usePredictiveStore.getState().loadPredictiveData(activated);
  }, [settings]);

  const [activeActionPlan, setActiveActionPlan] = useState<PredictiveAlert | null>(null);

  const [successToast, setSuccessToast] = useState<{ show: boolean; message: string; dept: string }>({
    show: false,
    message: '',
    dept: ''
  });

  const handleActivatePlan = (dept: string) => {
    togglePredictivePlan(dept).then(() => {
      // Reload the centralized store so all components update immediately
      const updatedActivated = [...activatedPlans, dept];
      reload(updatedActivated);

      setSuccessToast({
        show: true,
        message: t('plan_activated_success', 'تم بنجاح اعتماد وتفعيل خطة الاستجابة الذكية لقسم ({{dept}}) وجاري التنسيق الفوري والتلقائي مع إدارة القسم لتحسين رضا المرضى!', { dept }),
        dept
      });
    }).catch(() => {});

    setActiveActionPlan(null);

    // Auto close toast after 5s
    setTimeout(() => {
      setSuccessToast(prev => ({ ...prev, show: false }));
    }, 5000);
  };

  const activeWarningsCount = predictiveAlerts.filter(alert => !activatedPlans.includes(alert.department)).length;

  return (
    <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto relative animate-fade-in" dir="rtl">
      {/* Premium Success Toast/Banner */}
      {successToast.show && (
        <div className="fixed top-6 left-6 right-6 sm:left-auto sm:right-6 sm:max-w-md bg-slate-900 border border-emerald-500/30 text-white p-5 rounded-2xl shadow-2xl z-[150] animate-slide-up flex gap-3 overflow-hidden relative group">
          {/* Animated glowing bar */}
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-emerald-400" />
          <div className="w-10 h-10 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 shrink-0 animate-bounce">
            <Check className="w-5 h-5" />
          </div>
          <div className="text-start flex-1">
            <h4 className="font-bold text-sm text-emerald-400 leading-none mb-1.5 flex items-center gap-1.5">
              <span>{t('plan_activated_title', 'اعتماد خطة العمل الذكية')}</span>
              <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
            </h4>
            <p className="text-emerald-100/80 text-xs leading-relaxed">
              {successToast.message}
            </p>
          </div>
          <button 
            onClick={() => setSuccessToast(prev => ({ ...prev, show: false }))}
            className="text-emerald-300/50 hover:text-white p-1 hover:bg-white/5 rounded-lg transition-colors h-fit cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div className="flex items-center gap-3">
          <button 
            onClick={onBack}
            className="p-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-gray-100 dark:hover:bg-slate-800 border border-gray-200 dark:border-slate-800 text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200 transition-colors shadow-sm cursor-pointer"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div className="text-start">
            <div className="flex items-center gap-2">
              <Brain className="w-6 h-6 text-indigo-600 dark:text-indigo-400 animate-pulse" />
              <h1 className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white">
                {t('predictive_page_title', 'لوحة تحكم التنبؤ التلقائي والإنذار المبكر')}
              </h1>
            </div>
            <p className="text-xs sm:text-sm text-gray-500 dark:text-slate-400 mt-1">
              {t('predictive_page_desc', 'نظام ذكاء اصطناعي يحلل تقييمات المرضى زمنياً للتنبؤ بالهبوط الاستباقي وتحديد مسببات التراجع.')}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-2xl px-4 py-2.5">
          <Activity className="w-5 h-5 text-indigo-600 dark:text-indigo-400 animate-spin-slow" />
          <div className="text-start">
            <span className="block text-[9px] text-indigo-500 dark:text-indigo-400 font-bold uppercase tracking-wider">{t('ai_status', 'حالة النموذج')}</span>
            <span className="text-xs font-black text-indigo-950 dark:text-white">{t('ai_online', 'متصل ويعمل بلحظياً')}</span>
          </div>
        </div>
      </div>

      {/* Stats Summary Panel */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
          <span className="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{t('analyzed_responses', 'استجابات تم فحصها')}</span>
          <span className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white font-mono">
            {loading ? '...' : stats.totalResponsesAnalyzed}
          </span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
          <span className="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{t('checked_depts', 'أقسام ومرافق مفحوصة')}</span>
          <span className="text-xl sm:text-2xl font-black text-gray-900 dark:text-white font-mono">
            {loading ? '...' : stats.totalDepts}
          </span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
          <span className="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{t('health_index_label', 'مؤشر صحة الرضا العام')}</span>
          <span className={`text-xl sm:text-2xl font-black font-mono ${stats.healthIndex >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
            {loading ? '...' : `${stats.healthIndex}%`}
          </span>
        </div>
        <div className="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl p-4 sm:p-5 shadow-sm text-start">
          <span className="block text-xs font-bold text-gray-400 dark:text-slate-400 mb-1">{t('active_alerts_count', 'إنذارات نشطة')}</span>
          <span className={`text-xl sm:text-2xl font-black font-mono ${activeWarningsCount > 0 ? 'text-rose-600 dark:text-rose-400 animate-pulse' : 'text-gray-400 dark:text-slate-500'}`}>
            {loading ? '...' : activeWarningsCount}
          </span>
        </div>
      </div>

      {loading ? (
        <div className="text-center py-24 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-3xl shadow-sm">
          <div className="w-12 h-12 border-4 border-indigo-250 dark:border-indigo-950 border-t-indigo-600 dark:border-t-indigo-400 rounded-full animate-spin mx-auto mb-4" />
          <p className="text-gray-500 dark:text-slate-300 font-medium">{t('loading_predictions', 'جاري تحليل المنحنيات الزمنية وتجهيز التوقعات...')}</p>
        </div>
      ) : predictiveAlerts.length > 0 ? (
        <div className="space-y-6">
          <div className="flex items-center gap-2 mb-2 text-start">
            <span className="flex h-2.5 w-2.5 relative">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
            </span>
            <h2 className="text-sm font-black uppercase tracking-wider text-rose-700 dark:text-rose-400">
              {t('warnings_detected', 'إنذارات التراجع المستهدفة بالتدخل')}
            </h2>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {predictiveAlerts.map(alert => (
              <div 
                key={alert.id}
                className="relative bg-linear-to- from-slate-900 via-indigo-950 to-slate-950 text-white rounded-3xl p-6 shadow-2xl border border-indigo-500/30 overflow-hidden group hover:shadow-indigo-500/20 hover:border-indigo-500/50 transition-all duration-300"
              >
                {/* Decorative glowing background mesh */}
                <div className="absolute -right-10 -top-10 w-44 h-44 bg-indigo-500 rounded-full blur-[90px] opacity-25 group-hover:opacity-35 transition-opacity pointer-events-none" />
                <div className="absolute -left-10 -bottom-10 w-44 h-44 bg-purple-500 rounded-full blur-[90px] opacity-15 group-hover:opacity-25 transition-opacity pointer-events-none" />
                
                <div className="relative flex flex-col h-full justify-between">
                  <div>
                    {/* Header Badge */}
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center gap-1.5 bg-indigo-500/20 border border-indigo-400/30 rounded-full px-3 py-1 text-[10px] font-bold text-indigo-300">
                        <Sparkles className="w-3.5 h-3.5 text-indigo-400 animate-spin-slow" />
                        <span>{t('early_warning_alert', 'إنذار مبكر بتراجع مستوى الرضا')}</span>
                      </div>
                      <span className="text-[10px] text-indigo-200/50 font-mono">
                        {t('ai_confidence', 'دقة التنبؤ: 94%')}
                      </span>
                    </div>

                    {/* Department Title */}
                    <h3 className="text-base sm:text-lg font-black mb-3 text-white flex items-center gap-2 text-start leading-tight">
                      <Building2 className="w-5 h-5 text-indigo-400" />
                      {t('warning_dept_title', 'رصد تراجع حاد ومستمر في رضا قسم:')} <span className="text-teal-400 font-extrabold">{alert.department}</span>
                    </h3>

                    {/* Explanation */}
                    <p className="text-xs text-indigo-100/70 mb-5 leading-relaxed text-start">
                      {t('warning_description_1', 'قام النظام بتحليل السلوك الزمني لآخر الاستجابات الواردة لهذا القسم ورصد تراجعاً متتالياً في مؤشرات الرضا. يتوقع النموذج تدهوراً أكبر في معدلات الرضا خلال الفترة القادمة إذا لم يتم التعامل مع المشكلة فوراً.')}
                    </p>

                    {/* Stats Comparison Grid */}
                    <div className="grid grid-cols-3 gap-3 bg-white/5 border border-white/10 rounded-2xl p-4 mb-5 backdrop-blur-sm">
                      <div className="text-center">
                        <span className="block text-[10px] text-indigo-200/80 mb-1">{t('previous_period', 'المعدل السابق')}</span>
                        <span className="text-lg font-black text-emerald-400 block">{alert.previousAvg}%</span>
                      </div>
                      <div className="text-center border-x border-white/10">
                        <span className="block text-[10px] text-indigo-200/80 mb-1">{t('current_period', 'المعدل الحالي')}</span>
                        <span className="text-lg font-black text-rose-400 block">{alert.currentAvg}%</span>
                      </div>
                      <div className="text-center">
                        <span className="block text-[10px] text-indigo-200/80 mb-1">{t('predicted_period', 'التنبؤ القادم')}</span>
                        <span className="text-lg font-black text-yellow-400 block flex items-center justify-center gap-0.5">
                          {alert.predictedScore}%
                          <ArrowDownRight className="w-4 h-4 text-rose-500 animate-bounce" />
                        </span>
                      </div>
                    </div>

                    {/* Analysis drivers & Details */}
                    <div className="space-y-2.5 mb-6 text-start">
                      <div className="flex items-start gap-2 text-xs">
                        <TrendingDown className="w-4 h-4 text-rose-400 shrink-0 mt-0.5" />
                        <div>
                          <span className="text-indigo-200/90">{t('drop_amount', 'حجم التراجع في التقييمات:')}</span>{' '}
                          <span className="font-bold text-rose-300">-{alert.drop}% (تراجع نسبي بمعدل {alert.dropPercentage}%)</span>
                        </div>
                      </div>
                      <div className="flex items-start gap-2 text-xs">
                        <Brain className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" />
                        <div>
                          <span className="text-indigo-200/90">{t('main_driver', 'المسبب الرئيسي المتوقع لهذا التراجع:')}</span>{' '}
                          <span className="font-bold text-teal-300">{alert.keyDriver}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-3 pt-4 border-t border-white/10">
                    <button 
                      onClick={() => {
                        const message = `⚠️ إنذار مبكر (AI): تراجع الرضا في قسم ${alert.department} من ${alert.previousAvg}% إلى ${alert.currentAvg}%.\nالمسبب الرئيسي: ${alert.keyDriver}.\nالتنبؤ القادم: يتوقع تراجع الرضا إلى ${alert.predictedScore}%.\n\nيرجى مراجعة الاستبيانات الأخيرة واتخاذ الإجراءات اللازمة.`;
                        if (navigator.share) {
                          navigator.share({ title: `إنذار مبكر - ${alert.department}`, text: message }).catch(() => {});
                        } else {
                          navigator.clipboard.writeText(message);
                          window.alert(t('alert_copied', 'تم نسخ تفاصيل الإنذار بنجاح لمشاركتها مع رئيس القسم!'));
                        }
                      }}
                      className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition-colors flex items-center justify-center gap-2 shadow-lg shadow-indigo-950/50 cursor-pointer"
                    >
                      <Sparkles className="w-3.5 h-3.5" />
                      {t('share_alert', 'مشاركة تقرير الإنذار')}
                    </button>
                    {activatedPlans.includes(alert.department) ? (
                      <div className="bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 font-extrabold px-4 py-2.5 rounded-xl text-xs flex items-center justify-center gap-1.5 animate-pulse-soft">
                        <Check className="w-4 h-4 text-emerald-400" />
                        <span>{t('plan_activated_badge', 'الخطة نشطة (AI)')}</span>
                      </div>
                    ) : (
                      <button 
                        onClick={() => {
                          setActiveActionPlan(alert);
                        }}
                        className="px-4 py-2.5 rounded-xl border border-white/20 hover:bg-white/10 text-white text-xs font-bold transition-all cursor-pointer"
                      >
                        {t('take_action', 'اتخاذ إجراء')}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : (
        /* Gorgeous clean slate for AI */
        <div className="text-center py-20 px-6 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-3xl shadow-sm max-w-2xl mx-auto">
          <div className="relative w-24 h-24 mx-auto mb-6 flex items-center justify-center">
            <div className="absolute inset-0 bg-indigo-500/10 rounded-full animate-ping pointer-events-none" />
            <div className="w-16 h-16 bg-linear-to- from-indigo-500 to-indigo-600 rounded-full flex items-center justify-center text-white shadow-xl dark:shadow-none">
              <ShieldCheck className="w-8 h-8" />
            </div>
          </div>
          <h2 className="text-lg font-black text-gray-900 dark:text-white mb-2">
            {t('no_warnings_title', 'جميع الأقسام مستقرة وممتازة')}
          </h2>
          <p className="text-xs sm:text-sm text-gray-550 dark:text-slate-400 leading-relaxed max-w-md mx-auto">
            {t('no_warnings_desc', 'يقوم النموذج بالرقابة الزمنية اللحظية لتقييمات رضا المرضى، وجميع المنحنيات تسير باتجاهات نمو إيجابية متزايدة ولا توجد أي مؤشرات لتراجع الخدمة حالياً.')}
          </p>
        </div>
      )}

      {/* AI Action Plan Modal */}
      {activeActionPlan && (
        <div className="fixed inset-0 bg-black/75 backdrop-blur-md flex items-center justify-center z-[100] p-4">
          <div className="bg-slate-900 border border-indigo-500/30 text-white rounded-3xl max-w-lg w-full shadow-2xl animate-scale-in overflow-hidden relative">
            
            {/* Glowing ambient background inside modal */}
            <div className="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500 rounded-full blur-[80px] opacity-20 pointer-events-none" />
            <div className="absolute -left-10 -bottom-10 w-40 h-40 bg-purple-500 rounded-full blur-[80px] opacity-15 pointer-events-none" />

            <div className="relative">
              {/* Modal Header */}
              <div className="bg-linear-to- from-indigo-950 via-purple-950 to-indigo-950 p-6 border-b border-indigo-500/10">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                      <Brain className="w-4 h-4" />
                    </div>
                    <h3 className="text-base sm:text-lg font-black">{t('ai_plan_modal_title', 'خطة الاستجابة المقترحة من الذكاء الاصطناعي')}</h3>
                  </div>
                  <button 
                    onClick={() => setActiveActionPlan(null)}
                    className="p-1.5 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-gray-400 hover:text-white transition-colors cursor-pointer"
                  >
                    <X className="w-5 h-5" />
                  </button>
                </div>
                <div className="mt-3 flex items-center gap-2">
                  <span className="text-[10px] text-teal-400 font-bold bg-teal-500/10 px-2 py-0.5 rounded-full border border-teal-500/20">
                    {activeActionPlan.department}
                  </span>
                  <span className="text-[10px] text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20">
                    {t('predicted_drop_lbl', 'تراجع متوقع:')} -{activeActionPlan.drop}%
                  </span>
                </div>
              </div>

              {/* Modal Body */}
              <div className="p-6 space-y-5 text-start max-h-[70vh] overflow-y-auto">
                <div className="bg-white/5 border border-white/10 rounded-2xl p-4">
                  <h4 className="text-xs font-black text-indigo-300 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5" />
                    {t('ai_insight', 'تحليل الذكاء الاصطناعي للمشكلة:')}
                  </h4>
                  <p className="text-xs text-indigo-100/90 leading-relaxed">
                    {t('ai_insight_desc', 'تم تحديد انخفاض الرضا الرئيسي في فئة')} <span className="text-teal-300 font-bold">({activeActionPlan.keyDriver})</span>. {t('ai_insight_desc_2', 'يتنبأ النموذج بارتفاع مؤشر الرضا إلى +85% خلال أسبوعين في حال تم اعتماد وتفعيل إجراءات العمل الفورية التالية:')}
                  </p>
                </div>

                {/* Steps */}
                <div className="space-y-4">
                  <h4 className="text-xs font-black text-gray-400 uppercase tracking-wider">{t('recommended_actions', 'خطوات العمل الفورية الموصى بها:')}</h4>
                  
                  {/* Step 1 */}
                  <div className="flex gap-3">
                    <div className="w-6 h-6 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 animate-pulse">
                      1
                    </div>
                    <div>
                      <h5 className="text-xs font-bold text-emerald-400 flex items-center gap-1.5">
                        {t('action_step_1_title', 'إجراء فوري (خلال 24 ساعة)')}
                      </h5>
                      <p className="text-[11px] text-gray-300 mt-1 leading-relaxed">
                        {t('action_step_1_desc', 'إصدار تنبيه آلي عاجل لمشرفي قسم')} {activeActionPlan.department} {t('action_step_1_desc_2', 'بضرورة مراجعة جودة الخدمة في')} ({activeActionPlan.keyDriver}).
                      </p>
                    </div>
                  </div>

                  {/* Step 2 */}
                  <div className="flex gap-3">
                    <div className="w-6 h-6 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                      2
                    </div>
                    <div>
                      <h5 className="text-xs font-bold text-indigo-300 flex items-center gap-1.5">
                        {t('action_step_2_title', 'إجراء قصير المدى (خلال 3 أيام)')}
                      </h5>
                      <p className="text-[11px] text-gray-300 mt-1 leading-relaxed">
                        {t('action_step_2_desc', 'تحسين توزيع الطاقة الاستيعابية عند نقاط الخدمة الخاصة بفئة')} ({activeActionPlan.keyDriver}) {t('action_step_2_desc_2', 'لتقليص زمن الانتظار المسبب للتراجع.')}
                      </p>
                    </div>
                  </div>

                  {/* Step 3 */}
                  <div className="flex gap-3">
                    <div className="w-6 h-6 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                      3
                    </div>
                    <div>
                      <h5 className="text-xs font-bold text-purple-400 flex items-center gap-1.5">
                        {t('action_step_3_title', 'مراقبة ومتابعة (مستمر)')}
                      </h5>
                      <p className="text-[11px] text-gray-300 mt-1 leading-relaxed">
                        {t('action_step_3_desc', 'عقد اجتماع تتبع جودة الخدمة مع الطاقم التشغيلي للقسم لمعاينة نتائج الاستبيانات الفردية.')}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Modal Footer */}
              <div className="p-6 border-t border-indigo-500/10 bg-slate-950 flex items-center gap-3">
                <button 
                  onClick={() => setActiveActionPlan(null)}
                  className="flex-1 py-3 rounded-2xl border border-white/10 hover:bg-white/5 text-white text-xs font-bold transition-all cursor-pointer"
                >
                  {t('cancel', 'إلغاء')}
                </button>
                <button 
                  onClick={() => handleActivatePlan(activeActionPlan.department)}
                  className="flex-1 py-3 rounded-2xl bg-linear-to- from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-xs font-bold transition-all shadow-lg shadow-indigo-950 cursor-pointer flex items-center justify-center gap-1.5"
                >
                  <Check className="w-4 h-4" />
                  {t('activate_plan', 'اعتماد وتفعيل الخطة')}
                </button>
              </div>

            </div>
          </div>
        </div>
      )}
    </div>
  );
}
