import { SurveyResponse, DashboardStats } from '../types';

export const analyticsService = {
  /**
   * Calculates dashboard analytics, metrics, satisfaction distribution and NPS score
   * based on a set of frontend survey responses.
   */
  calculateDashboardStats(responses: SurveyResponse[]): DashboardStats {
    const totalResponses = responses.length;
    const averageScore = totalResponses > 0 
      ? Math.round(responses.reduce((sum, r) => sum + r.overallScore, 0) / totalResponses) 
      : 0;

    // Period comparison (simulation: split current data into two halves)
    const midpoint = Math.floor(responses.length / 2);
    const previousResponses = responses.slice(midpoint);
    const previousAverageScore = previousResponses.length > 0
      ? Math.round(previousResponses.reduce((sum, r) => sum + r.overallScore, 0) / previousResponses.length)
      : 0;

    // ── Smart NPS Heuristic ──
    // Identify which question keys are NPS questions by scanning if any response has a value > 5 or === 0 for that key.
    const npsKeys = new Set<string>();
    responses.forEach(r => {
      Object.entries(r.answers).forEach(([key, val]) => {
        if (typeof val === 'number' && (val === 0 || val > 5)) {
          npsKeys.add(key);
        }
      });
    });

    let promoters = 0;
    let detractors = 0;
    let npsTotal = 0;

    responses.forEach(r => {
      // Find any answer that corresponds to an identified NPS key
      let npsVal: number | undefined;
      for (const key of npsKeys) {
        const val = r.answers[key];
        if (typeof val === 'number') {
          npsVal = val;
          break;
        }
      }

      if (npsVal !== undefined) {
        if (npsVal >= 9) promoters++;
        else if (npsVal <= 6) detractors++;
        npsTotal++;
      }
    });

    const npsScore = npsTotal > 0 
      ? Math.round(((promoters - detractors) / npsTotal) * 100)
      : 0;

    // Previous NPS (calculated from older half of data by time)
    const sortedForNps = responses.filter(r => {
      return Object.keys(r.answers).some(key => npsKeys.has(key));
    }).sort((a, b) => new Date(a.submittedAt).getTime() - new Date(b.submittedAt).getTime());
    
    let prevNpsScore = 0;
    if (sortedForNps.length > 0) {
      const minNpsDate = new Date(sortedForNps[0].submittedAt).getTime();
      const maxNpsDate = new Date(sortedForNps[sortedForNps.length - 1].submittedAt).getTime();
      const npsMidpointTime = minNpsDate + (maxNpsDate - minNpsDate) / 2;
      
      const olderNpsResponses = sortedForNps.filter(r => new Date(r.submittedAt).getTime() < npsMidpointTime);
      
      let prevPromoters = 0;
      let prevDetractors = 0;
      let prevNpsTotal = 0;

      olderNpsResponses.forEach(r => {
        let npsVal: number | undefined;
        for (const key of npsKeys) {
          const val = r.answers[key];
          if (typeof val === 'number') {
            npsVal = val;
            break;
          }
        }
        if (npsVal !== undefined) {
          if (npsVal >= 9) prevPromoters++;
          else if (npsVal <= 6) prevDetractors++;
          prevNpsTotal++;
        }
      });

      prevNpsScore = prevNpsTotal > 0
        ? Math.round(((prevPromoters - prevDetractors) / prevNpsTotal) * 100)
        : 0;
    }

    // Department scores
    const deptMap = new Map<string, { total: number; count: number }>();
    responses.forEach(r => {
      const existing = deptMap.get(r.department) || { total: 0, count: 0 };
      existing.total += r.overallScore;
      existing.count += 1;
      deptMap.set(r.department, existing);
    });
    const departmentScores = Array.from(deptMap.entries()).map(([name, data]) => ({
      name,
      score: Math.round(data.total / data.count),
      count: data.count,
    })).sort((a, b) => b.score - a.score);

    // Hourly Stats (Advanced Reporting)
    const hourlyMap = new Map<number, { total: number; count: number }>();
    for (let i = 0; i < 24; i++) hourlyMap.set(i, { total: 0, count: 0 });
    
    responses.forEach(r => {
      const date = new Date(r.submittedAt);
      const hour = date.getHours();
      const stats = hourlyMap.get(hour)!;
      stats.total += r.overallScore;
      stats.count += 1;
    });

    const hourlyStats = Array.from(hourlyMap.entries()).map(([hour, data]) => ({
      hour: `${hour}:00`,
      score: data.count > 0 ? Math.round(data.total / data.count) : 0,
      count: data.count,
    }));

    // Day Stats (Advanced Reporting)
    const daysAr = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const dayMap = new Map<number, { total: number; count: number }>();
    for (let i = 0; i < 7; i++) dayMap.set(i, { total: 0, count: 0 });

    responses.forEach(r => {
      const date = new Date(r.submittedAt);
      const day = date.getDay();
      const stats = dayMap.get(day)!;
      stats.total += r.overallScore;
      stats.count += 1;
    });

    const dayStats = Array.from(dayMap.entries()).map(([day, data]) => ({
      day: daysAr[day],
      score: data.count > 0 ? Math.round(data.total / data.count) : 0,
      count: data.count,
    }));

    // Trend data (last 12 weeks)
    const trendData: { date: string; score: number; count: number }[] = [];
    for (let i = 11; i >= 0; i--) {
      const weekStart = new Date();
      weekStart.setDate(weekStart.getDate() - i * 7);
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekEnd.getDate() + 7);
      
      const weekLabel = `${weekStart.getDate()}/${weekStart.getMonth() + 1}`;
      const weekResponses = responses.filter(r => {
        const d = new Date(r.submittedAt);
        return d >= weekStart && d < weekEnd;
      });
      
      trendData.push({
        date: weekLabel,
        score: weekResponses.length > 0 
          ? Math.round(weekResponses.reduce((s, r) => s + r.overallScore, 0) / weekResponses.length)
          : 0,
        count: weekResponses.length,
      });
    }

    // Category scores
    const categories = [
      { category: 'الاستقبال', keys: ['q1', 'q2', 'q3'] },
      { category: 'الرعاية الطبية', keys: ['q4', 'q5', 'q6', 'q7'] },
      { category: 'المرافق', keys: ['q8', 'q9', 'q10'] },
      { category: 'الصيدلية', keys: ['q11'] },
    ];
    
    const categoryScores = categories.map(cat => {
      let total = 0;
      let count = 0;
      responses.forEach(r => {
        cat.keys.forEach(k => {
          const val = r.answers[k];
          if (typeof val === 'number') {
            total += val;
            count++;
          }
        });
      });
      return {
        category: cat.category,
        score: count > 0 ? Math.round((total / count) * 20) : 0,
      };
    });

    // Satisfaction distribution
    const excellent = responses.filter(r => r.overallScore >= 85).length;
    const good = responses.filter(r => r.overallScore >= 70 && r.overallScore < 85).length;
    const average = responses.filter(r => r.overallScore >= 50 && r.overallScore < 70).length;
    const poor = responses.filter(r => r.overallScore < 50).length;

    const satisfactionDistribution = [
      { level: 'ممتاز', count: excellent, color: '#10B981' },
      { level: 'جيد', count: good, color: '#3B82F6' },
      { level: 'متوسط', count: average, color: '#F59E0B' },
      { level: 'ضعيف', count: poor, color: '#EF4444' },
    ];

    // Response rate
    const sortedByDate = [...responses].sort((a, b) => new Date(a.submittedAt).getTime() - new Date(b.submittedAt).getTime());
    let responseRate = 0;
    if (sortedByDate.length > 0) {
      const minDate = new Date(sortedByDate[0].submittedAt).getTime();
      const maxDate = new Date(sortedByDate[sortedByDate.length - 1].submittedAt).getTime();
      const timeSpan = maxDate - minDate;
      
      if (timeSpan > 0) {
        const midpointTime = minDate + timeSpan / 2;
        const olderHalf = sortedByDate.filter(r => new Date(r.submittedAt).getTime() < midpointTime);
        const newerHalf = sortedByDate.filter(r => new Date(r.submittedAt).getTime() >= midpointTime);
        
        responseRate = olderHalf.length > 0
          ? Math.round((newerHalf.length / olderHalf.length) * 100)
          : (newerHalf.length > 0 ? 100 : 0);
      } else {
        responseRate = 100;
      }
    }

    return {
      totalResponses,
      averageScore,
      previousAverageScore,
      npsScore,
      previousNpsScore: prevNpsScore,
      responseRate,
      departmentScores,
      trendData,
      categoryScores,
      satisfactionDistribution,
      hourlyStats,
      dayStats,
    };
  }
};
