import { useState, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { 
  Trophy, 
  Award, 
  Medal, 
  Star, 
  Users,
  Building2,
  TrendingUp,
  Search
} from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';
import { createLogger } from '../utils/logger';

const logger = createLogger('HallOfFamePage');


// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface HallOfFamePageProps {}

type TimeFilter = 'all' | 'week' | 'month' | 'year' | 'custom';

export default function HallOfFamePage(_props: HallOfFamePageProps) {
  const { t } = useTranslation();
  const { currentUser } = useAuthStore();
  const [timeFilter, setTimeFilter] = useState<TimeFilter>('all');
  const [customStartDate, setCustomStartDate] = useState('');
  const [customEndDate, setCustomEndDate] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  const [departmentScores, setDepartmentScores] = useState<{name: string, score: number, count: number}[]>([]);

  useEffect(() => {
    let startDate: string | undefined;
    let endDate: string | undefined;

    if (timeFilter === 'custom') {
      startDate = customStartDate || undefined;
      endDate = customEndDate || undefined;
    } else if (timeFilter !== 'all') {
      const start = new Date();
      switch (timeFilter) {
        case 'week':
          start.setDate(start.getDate() - 7);
          break;
        case 'month':
          start.setMonth(start.getMonth() - 1);
          break;
        case 'year':
          start.setFullYear(start.getFullYear() - 1);
          break;
      }
      startDate = start.toISOString();
    }

    import('../api/client').then(({ responsesAPI }) => {
      responsesAPI.getStats({ startDate, endDate }).then(stats => {
        setDepartmentScores(stats.departmentScores);
      }).catch(err => {
        logger.error('Failed to load stats:', err);
      });
    });
  }, [timeFilter, customStartDate, customEndDate]);

  const departmentLeaderboard = useMemo(() => {
    return departmentScores
      .filter(d => d.name.toLowerCase().includes(searchTerm.toLowerCase()))
      .sort((a, b) => b.score - a.score);
  }, [departmentScores, searchTerm]);

  const topThree = departmentLeaderboard.slice(0, 3);

  const myDeptIndex = useMemo(() => {
    if (!currentUser?.department) return -1;
    return departmentLeaderboard.findIndex(
      d => d.name.trim().toLowerCase() === currentUser.department!.trim().toLowerCase()
    );
  }, [departmentLeaderboard, currentUser]);

  const myDeptData = useMemo(() => {
    if (myDeptIndex === -1) return null;
    return departmentLeaderboard[myDeptIndex];
  }, [departmentLeaderboard, myDeptIndex]);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-start">
      {/* Header & Filters */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-10">
        <div>
          <div className="flex items-center gap-3 mb-2">
            <div className="w-12 h-12 bg-yellow-100 dark:bg-yellow-950/20 rounded-2xl flex items-center justify-center shadow-sm">
              <Trophy className="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
            </div>
            <h1 className="text-2xl font-black text-gray-900 dark:text-white">{t('hof_title')}</h1>
          </div>
          <p className="text-gray-500 dark:text-slate-400">{t('hof_subtitle')}</p>
        </div>

        <div className="flex flex-col sm:flex-row items-center gap-3">
          {/* Search (Hidden for head of department as they only see their own department) */}
          {currentUser?.role !== 'head_of_department' && (
            <div className="relative w-full sm:w-64">
              <Search className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input 
                type="text"
                placeholder={t('hof_search_placeholder')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 dark:border-slate-700 focus:border-yellow-500 focus:ring-4 focus:ring-yellow-50 dark:focus:ring-yellow-950/15 outline-none text-sm transition-all bg-white dark:bg-slate-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-550"
              />
            </div>
          )}

          {/* Time Filter */}
          <div className="flex flex-col sm:flex-row items-center bg-white dark:bg-slate-950 p-1 rounded-xl border border-gray-100 dark:border-slate-800 shadow-sm w-full sm:w-auto gap-1">
            <div className="flex items-center gap-1 w-full sm:w-auto">
              {[
                { id: 'all', label: t('hof_filter_all') },
                { id: 'week', label: t('hof_filter_week') },
                { id: 'month', label: t('hof_filter_month') },
                { id: 'custom', label: t('hof_filter_custom') },
              ].map((f) => (
                <button
                  key={f.id}
                  onClick={() => setTimeFilter(f.id as TimeFilter)}
                  type="button"
                  className={`flex-1 sm:flex-none px-3 py-2 rounded-lg text-[10px] sm:text-xs font-bold transition-all cursor-pointer ${
                    timeFilter === f.id 
                      ? 'bg-yellow-500 text-white shadow-md' 
                      : 'text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:hover:bg-slate-800'
                  }`}
                >
                  {f.label}
                </button>
              ))}
            </div>

            {timeFilter === 'custom' && (
              <div className="flex items-center gap-2 px-2 border-r border-gray-100 dark:border-slate-800 animate-fade-in py-1 sm:py-0">
                <div className="flex items-center gap-1">
                  <span className="text-[10px] text-gray-400 dark:text-slate-500">{t('hof_from_label')}</span>
                  <input 
                    type="date" 
                    value={customStartDate}
                    onChange={(e) => setCustomStartDate(e.target.value)}
                    className="text-[10px] border border-gray-200 dark:border-slate-700 rounded px-1 py-0.5 outline-none focus:border-yellow-500 bg-white dark:bg-slate-850 text-gray-900 dark:text-white"
                  />
                </div>
                <div className="flex items-center gap-1">
                  <span className="text-[10px] text-gray-400 dark:text-slate-500">{t('hof_to_label')}</span>
                  <input 
                    type="date" 
                    value={customEndDate}
                    onChange={(e) => setCustomEndDate(e.target.value)}
                    className="text-[10px] border border-gray-200 dark:border-slate-700 rounded px-1 py-0.5 outline-none focus:border-yellow-500 bg-white dark:bg-slate-850 text-gray-900 dark:text-white"
                  />
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {currentUser?.role === 'head_of_department' && currentUser.department ? (
        myDeptData ? (
          <div className="max-w-4xl mx-auto space-y-8 animate-fade-in mt-6">
            {/* Rank Spotlight Card */}
            <div className={`relative overflow-hidden rounded-3xl p-8 text-white shadow-2xl transition-all duration-500 ${
              (myDeptIndex + 1) === 1 ? 'bg-linear-to- from-yellow-500 via-amber-500 to-yellow-600 shadow-yellow-200 border border-yellow-400' :
              (myDeptIndex + 1) === 2 ? 'bg-linear-to- from-slate-400 via-slate-500 to-slate-600 shadow-slate-200 border border-slate-300' :
              (myDeptIndex + 1) === 3 ? 'bg-linear-to- from-orange-400 via-amber-600 to-amber-700 shadow-amber-200 border border-orange-500' :
              'bg-linear-to- from-teal-600 via-emerald-600 to-teal-700 border border-teal-500'
            }`}>
              {/* Decorative Background Elements */}
              <div className="absolute -right-10 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none" />
              <div className="absolute right-6 top-6 opacity-15 pointer-events-none">
                {(myDeptIndex + 1) === 1 ? <Trophy className="w-32 h-32" /> :
                 (myDeptIndex + 1) === 2 ? <Award className="w-32 h-32" /> :
                 (myDeptIndex + 1) === 3 ? <Medal className="w-32 h-32" /> :
                 <Building2 className="w-32 h-32" />}
              </div>

              <div className="relative flex flex-col md:flex-row items-center justify-between gap-6">
                <div className="text-center md:text-right">
                  <span className="inline-block px-3 py-1 bg-white/20 rounded-full text-xs font-black tracking-widest uppercase mb-3">
                    {currentUser.department}
                  </span>
                  <h2 className="text-2xl font-black mb-2">أداء القسم وترتيبه الحالي</h2>
                  <p className="text-white/80 text-sm max-w-md">
                    {(myDeptIndex + 1) === 1 ? 'تهانينا الحارة! يحتل قسمكم المركز الأول بجدارة وتميز تام.' :
                     (myDeptIndex + 1) <= 3 ? 'رائع جداً! قسمكم ضمن المراكز الثلاثة الأولى الأكثر تميزاً في المستشفى.' :
                     'أداء متميز وجهود مباركة! نسعى دائماً للوصول للقمة وتقديم أفضل رعاية للمرضا.'}
                  </p>
                </div>

                <div className="flex flex-col items-center justify-center bg-white/10 backdrop-blur-md rounded-2xl p-6 min-w-[200px] border border-white/10">
                  <span className="text-xs font-bold text-white/70 uppercase tracking-widest mb-1">الترتيب في لوحة الشرف</span>
                  <div className="flex items-baseline gap-1">
                    <span className="text-5xl font-black leading-none">{myDeptIndex + 1}</span>
                    <span className="text-lg font-bold text-white/80">/ {departmentLeaderboard.length}</span>
                  </div>
                  <div className="flex gap-0.5 mt-3">
                    {[1, 2, 3, 4, 5].map(s => (
                      <Star key={s} className={`w-4 h-4 ${s <= Math.round(myDeptData.score / 20) ? 'text-yellow-300 fill-yellow-300' : 'text-white/20'}`} />
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {/* Score & Detailed Stats Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              {/* Satisfaction Score */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-6 flex items-center justify-between shadow-sm">
                <div>
                  <span className="text-xs text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider block mb-1">نسبة رضا المرضى</span>
                  <span className="text-3xl font-black text-gray-900 dark:text-white">{myDeptData.score}%</span>
                </div>
                <div className="w-16 h-16 rounded-2xl bg-teal-50 dark:bg-teal-950/20 flex items-center justify-center text-teal-600 dark:text-teal-400">
                  <TrendingUp className="w-8 h-8" />
                </div>
              </div>

              {/* Response Count */}
              <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-6 flex items-center justify-between shadow-sm">
                <div>
                  <span className="text-xs text-gray-400 dark:text-slate-500 font-bold uppercase tracking-wider block mb-1">عدد استجابات المرضى</span>
                  <span className="text-3xl font-black text-gray-900 dark:text-white">{myDeptData.count}</span>
                </div>
                <div className="w-16 h-16 rounded-2xl bg-blue-50 dark:bg-blue-950/20 flex items-center justify-center text-blue-600 dark:text-blue-400">
                  <Users className="w-8 h-8" />
                </div>
              </div>
            </div>
          </div>
        ) : (
          <div className="bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 p-12 text-center mt-6">
            <Building2 className="w-12 h-12 text-gray-300 dark:text-slate-600 mx-auto mb-4" />
            <h3 className="text-lg font-bold text-gray-850 dark:text-white mb-1">لا توجد بيانات متاحة حالياً</h3>
            <p className="text-gray-500 dark:text-slate-400 text-sm">لم يتم تسجيل أي استجابات أو تقييمات لقسم {currentUser.department} بعد في هذه الفترة.</p>
          </div>
        )
      ) : (
        <>
          {/* Top 3 Podiums */}
          {departmentLeaderboard.length > 0 ? (
            <div className="mb-12">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                {/* Second Place */}
                {topThree[1] && (
                  <div className="order-2 md:order-1 flex flex-col items-center">
                    <div className="relative mb-4">
                      <div className="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-850 border-4 border-slate-300 dark:border-slate-700 flex items-center justify-center shadow-lg">
                        <Award className="w-10 h-10 text-slate-400" />
                      </div>
                      <div className="absolute -bottom-2 -right-2 bg-slate-400 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 border-white">2</div>
                    </div>
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800/80 p-5 w-full text-center shadow-sm hover:shadow-md transition-all">
                      <h3 className="font-black text-gray-800 dark:text-white mb-1">{topThree[1].name}</h3>
                      <div className="text-2xl font-black text-slate-500 dark:text-slate-400 mb-2">{topThree[1].score}%</div>
                      <div className="flex justify-center gap-0.5">
                        {[1,2,3,4,5].map(s => <Star key={s} className={`w-3 h-3 ${s <= Math.round(topThree[1].score / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700'}`} />)}
                      </div>
                    </div>
                  </div>
                )}

                {/* First Place */}
                {topThree[0] && (
                  <div className="order-1 md:order-2 flex flex-col items-center mb-6 md:mb-0 scale-110">
                    <div className="relative mb-6">
                      <div className="absolute -top-6 left-1/2 -translate-x-1/2 animate-bounce">
                        <Trophy className="w-8 h-8 text-yellow-500 fill-yellow-250" />
                      </div>
                      <div className="w-24 h-24 rounded-full bg-yellow-50 dark:bg-yellow-950/20 border-4 border-yellow-400 flex items-center justify-center shadow-xl ring-8 ring-yellow-400/10">
                        <Building2 className="w-12 h-12 text-yellow-600 dark:text-yellow-400" />
                      </div>
                      <div className="absolute -bottom-2 -right-2 bg-yellow-500 text-white w-10 h-10 rounded-full flex items-center justify-center font-bold border-4 border-white shadow-lg">1</div>
                    </div>
                    <div className="bg-linear-to- from-yellow-500 to-yellow-600 rounded-3xl p-6 w-full text-center shadow-xl border-2 border-yellow-450">
                      <h3 className="font-black text-white text-xl mb-1">{topThree[0].name}</h3>
                      <div className="text-3xl font-black text-white mb-2">{topThree[0].score}%</div>
                      <div className="flex justify-center gap-1">
                        {[1,2,3,4,5].map(s => <Star key={s} className={`w-4 h-4 ${s <= Math.round(topThree[0].score / 20) ? 'text-yellow-200 fill-yellow-200' : 'text-yellow-700'}`} />)}
                      </div>
                    </div>
                  </div>
                )}

                {/* Third Place */}
                {topThree[2] && (
                  <div className="order-3 flex flex-col items-center">
                    <div className="relative mb-4">
                      <div className="w-20 h-20 rounded-full bg-orange-50 dark:bg-orange-950/20 border-4 border-orange-300 dark:border-orange-850 flex items-center justify-center shadow-lg">
                        <Medal className="w-10 h-10 text-orange-400" />
                      </div>
                      <div className="absolute -bottom-2 -right-2 bg-orange-400 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 border-white">3</div>
                    </div>
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-orange-200 dark:border-orange-950/45 p-5 w-full text-center shadow-sm hover:shadow-md transition-all">
                      <h3 className="font-black text-gray-850 dark:text-white mb-1">{topThree[2].name}</h3>
                      <div className="text-2xl font-black text-orange-500 dark:text-orange-400 mb-2">{topThree[2].score}%</div>
                      <div className="flex justify-center gap-0.5">
                        {[1,2,3,4,5].map(s => <Star key={s} className={`w-3 h-3 ${s <= Math.round(topThree[2].score / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700'}`} />)}
                      </div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          ) : null}

          {/* Full Leaderboard Table */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl border border-gray-100 dark:border-slate-800 shadow-sm overflow-hidden">
            <div className="p-6 border-b border-gray-50 dark:border-slate-800/80 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <TrendingUp className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                <h2 className="font-bold text-gray-800 dark:text-white">{t('hof_table_title')}</h2>
              </div>
              <span className="text-xs text-gray-400 dark:text-slate-500">{departmentLeaderboard.length} {t('hof_table_count_suffix')}</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="bg-gray-50/50 dark:bg-slate-850/40">
                    <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest w-20">{t('hof_table_header_rank')}</th>
                    <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{t('hof_table_header_dept')}</th>
                    <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">{t('hof_table_header_responses')}</th>
                    <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest w-48">{t('hof_table_header_satisfaction')}</th>
                    <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest text-center">{t('hof_table_header_rating')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-50 dark:divide-slate-800/60">
                  {departmentLeaderboard.map((dept, index) => (
                    <tr key={dept.name} className="hover:bg-gray-50/80 dark:hover:bg-slate-850/50 transition-colors group">
                      <td className="px-6 py-4">
                        <span className={`flex items-center justify-center w-8 h-8 rounded-lg font-black text-sm ${
                          index === 0 ? 'bg-yellow-500 text-white' : 
                          index === 1 ? 'bg-slate-300 dark:bg-slate-700 text-slate-700 dark:text-slate-300' : 
                          index === 2 ? 'bg-orange-300 dark:bg-orange-950/50 text-orange-800 dark:text-orange-400' : 
                          'bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400'
                        }`}>
                          {index + 1}
                        </span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-950/20 flex items-center justify-center text-teal-600 dark:text-teal-400 border border-teal-100 dark:border-teal-900/30 font-bold text-xs">
                            {dept.name.charAt(0)}
                          </div>
                          <span className="font-black text-gray-900 dark:text-white">{dept.name}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-500 dark:text-slate-400">
                        <div className="flex items-center gap-2">
                          <Users className="w-4 h-4 text-gray-300 dark:text-slate-600" />
                          {dept.count} {t('hof_patient_count_suffix')}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className="flex-1 h-2 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div 
                              className={`h-full rounded-full transition-all duration-1000 ${
                                dept.score >= 85 ? 'bg-green-500' : 
                                dept.score >= 70 ? 'bg-blue-500' : 
                                'bg-yellow-500'
                              }`}
                              style={{ width: `${dept.score}%` }}
                            />
                          </div>
                          <span className="font-black text-gray-900 dark:text-white text-sm">{dept.score}%</span>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex justify-center gap-0.5 opacity-40 group-hover:opacity-100 transition-opacity">
                          {[1,2,3,4,5].map(s => <Star key={s} className={`w-3.5 h-3.5 ${s <= Math.round(dept.score / 20) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200 dark:text-slate-700'}`} />)}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {departmentLeaderboard.length === 0 && (
            <div className="text-center py-20">
              <div className="w-20 h-20 bg-gray-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <Building2 className="w-10 h-10 text-gray-300 dark:text-slate-600" />
              </div>
              <p className="text-gray-500 dark:text-slate-400 font-bold">{t('hof_no_data_msg')}</p>
            </div>
          )}
        </>
      )}
    </div>
  );
}
