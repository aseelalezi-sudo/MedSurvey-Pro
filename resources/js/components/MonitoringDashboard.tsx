import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { 
  Activity, 
  Server, 
  Database, 
  Cpu, 
  Zap, 
  Clock, 
  RefreshCcw,
  CheckCircle2,
  AlertCircle,
  HardDrive
} from 'lucide-react';
import { monitoringAPI } from '../api/client';
import type { HealthData } from '../api/modules/monitoring';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip } from 'recharts';
import SafeResponsiveContainer from './SafeResponsiveContainer';
import { createLogger } from '../utils/logger';

const logger = createLogger('MonitoringDashboard');

export default function MonitoringDashboard() {
  const { t } = useTranslation();
  const [data, setData] = useState<HealthData | null>(null);
  const [history, setHistory] = useState<{ time: string; latency: number }[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchData = async () => {
    try {
      const res = await monitoringAPI.getHealth();
      setData(res);
      const time = new Date().toLocaleTimeString('ar-SA', { hour12: false });
      setHistory(prev => [...prev.slice(-19), { time, latency: res.totalLatencyMs }]);
      setLoading(false);
    } catch (err) {
      logger.error('Failed to fetch health data:', err);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 5000);
    return () => clearInterval(interval);
  }, []);

  const formatUptime = (seconds: number) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m}m`;
  };

  const formatMaybeMb = (value?: number | null) => (
    typeof value === 'number' ? `${value} MB` : t('not_available', 'غير متاح')
  );

  const systemHealthy = data?.status === 'ok';

  if (loading && !data) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <RefreshCcw className="w-8 h-8 text-teal-500 animate-spin" />
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-3 bg-teal-500/10 rounded-2xl">
            <Activity className="w-6 h-6 text-teal-500" />
          </div>
          <div className="text-right">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              {t('monitoring_title', 'لوحة مراقبة أداء النظام')}
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {t('monitoring_subtitle', 'متابعة حية للصحة التقنية وسرعة الاستجابة')}
            </p>
          </div>
        </div>
        <div className={`flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold ${
          systemHealthy
            ? 'bg-green-500/10 border border-green-500/20 text-green-600'
            : 'bg-amber-500/10 border border-amber-500/20 text-amber-600'
        }`}>
          {systemHealthy ? <CheckCircle2 className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
          <span>{systemHealthy ? t('system_online', 'النظام يعمل بشكل مثالي') : t('system_degraded', 'النظام يعمل مع تنبيه')}</span>
        </div>
      </div>

      {/* Main Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard 
          icon={<Server className="w-5 h-5 text-blue-500" />}
          label={t('uptime', 'وقت التشغيل')}
          value={typeof data?.system.uptime === 'number' ? formatUptime(data.system.uptime) : t('not_available', 'غير متاح')}
          subValue={t('os_platform', 'نظام: {{platform}}', { platform: data?.system.os.platform })}
        />
        <StatCard 
          icon={<Zap className="w-5 h-5 text-yellow-500" />}
          label={t('api_latency', 'سرعة الاستجابة')}
          value={`${data?.totalLatencyMs}ms`}
          subValue={t('real_time', 'تحديث مباشر كل 5 ثوانٍ')}
          trend={data?.totalLatencyMs && data.totalLatencyMs > 100 ? 'up' : 'down'}
        />
        <StatCard 
          icon={<Cpu className="w-5 h-5 text-purple-500" />}
          label={t('memory_usage', 'استهلاك الذاكرة')}
          value={formatMaybeMb(data?.system.memory.heapUsedMb)}
          subValue={data?.system.memory.heapTotalMb
            ? t('heap_total', 'من حد PHP {{total}} MB', { total: data.system.memory.heapTotalMb })
            : t('php_memory_limit_unavailable', 'حد ذاكرة PHP غير متاح')}
        />
        <StatCard 
          icon={<HardDrive className="w-5 h-5 text-rose-500" />}
          label={t('free_os_mem', 'الذاكرة الحرة (OS)')}
          value={formatMaybeMb(data?.system.os.freeMemMb)}
          subValue={t('system_load', 'استهلاك موارد الخادم')}
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Latency Chart */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
            <Clock className="w-5 h-5 text-teal-500" />
            {t('latency_history', 'تاريخ سرعة الاستجابة (ms)')}
          </h3>
          <div className="h-[250px] w-full">
            <SafeResponsiveContainer width="100%" height="100%">
              <AreaChart data={history}>
                <defs>
                  <linearGradient id="colorLatency" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#14b8a6" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#14b8a6" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#33415520" />
                <XAxis dataKey="time" hide />
                <YAxis stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                <Tooltip 
                  contentStyle={{ backgroundColor: '#0f172a', border: 'none', borderRadius: '12px', color: '#fff' }}
                  itemStyle={{ color: '#14b8a6' }}
                />
                <Area type="monotone" dataKey="latency" stroke="#14b8a6" strokeWidth={3} fillOpacity={1} fill="url(#colorLatency)" />
              </AreaChart>
            </SafeResponsiveContainer>
          </div>
        </div>

        {/* Infrastructure Status */}
        <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm space-y-6">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ShieldCheck className="w-5 h-5 text-teal-500" />
            {t('infrastructure_status', 'حالة البنية التحتية')}
          </h3>
          
          <ServiceStatus 
            icon={<Database className="w-5 h-5" />}
            name={t('database_mysql', 'قاعدة بيانات MySQL')}
            status={data?.services.database.status || 'unknown'}
            details={typeof data?.services.database.latencyMs === 'number'
              ? `${data.services.database.latencyMs}ms latency`
              : t('not_available', 'غير متاح')}
          />

          <ServiceStatus 
            icon={<RefreshCcw className="w-5 h-5" />}
            name={t('cache_service', 'خدمة التخزين المؤقت')}
            status={data?.services.cache.status || 'unknown'}
            details={t('cache_driver', 'المشغل: {{type}}', { type: data?.services.cache.type || 'unknown' })}
          />

          <div className="p-4 bg-blue-500/5 border border-blue-500/10 rounded-2xl">
            <div className="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs mb-1">
              <AlertCircle className="w-4 h-4" />
              <span>{t('monitoring_note', 'ملاحظة')}</span>
            </div>
            <p className="text-[10px] text-blue-800/60 dark:text-blue-300/60 leading-relaxed">
              {t('monitoring_desc', 'هذه البيانات حية ومستمدة من خادم MedSurvey Pro مباشرة. يتم تحديثها آلياً لضمان أفضل أداء للمرضا والمراجعين.')}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function StatCard({ icon, label, value, subValue, trend }: { icon: React.ReactNode, label: string, value: string, subValue: string, trend?: 'up' | 'down' }) {
  return (
    <div className="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 p-5 rounded-3xl shadow-sm hover:shadow-md transition-shadow">
      <div className="flex items-center gap-3 mb-4">
        <div className="p-2 bg-gray-50 dark:bg-slate-800 rounded-xl">
          {icon}
        </div>
        <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</span>
      </div>
      <div className="flex items-end justify-between">
        <div>
          <div className="text-2xl font-black text-gray-900 dark:text-white">{value}</div>
          <div className="text-[10px] text-gray-400 mt-1">{subValue}</div>
        </div>
        {trend && (
          <div className={`text-[10px] font-bold px-2 py-1 rounded-lg ${trend === 'down' ? 'bg-green-500/10 text-green-600' : 'bg-red-500/10 text-red-600'}`}>
            {trend === 'down' ? '▼ Good' : '▲ High'}
          </div>
        )}
      </div>
    </div>
  );
}

function ServiceStatus({ icon, name, status, details }: { icon: React.ReactNode, name: string, status: string, details: string }) {
  const isHealthy = status === 'healthy' || status === 'ok' || status === 'fallback';
  const isWarning = status === 'fallback';

  return (
    <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-slate-800/50 rounded-2xl border border-gray-100 dark:border-slate-800">
      <div className="flex items-center gap-3">
        <div className={`p-2 rounded-xl ${isHealthy ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'}`}>
          {icon}
        </div>
        <div>
          <div className="text-xs font-bold text-gray-900 dark:text-white">{name}</div>
          <div className="text-[10px] text-gray-400">{details}</div>
        </div>
      </div>
      <div className={`w-2 h-2 rounded-full ${isHealthy ? (isWarning ? 'bg-yellow-500 animate-pulse' : 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]') : 'bg-red-500'}`} />
    </div>
  );
}

function ShieldCheck({ className }: { className?: string }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
  );
}
