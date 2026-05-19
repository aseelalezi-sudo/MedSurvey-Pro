import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';
import { npsService } from './npsService.js';

interface StatsQuery {
  department?: string;
  startDate?: string;
  endDate?: string;
  [key: string]: unknown;
}

export const statsService = {
  /**
   * Generates comprehensive dashboard statistics.
   */
  async getDashboardStats(userRole: string, userDept: string | null, query: StatsQuery, tenantId?: string | null) {
    const { department, startDate, endDate } = query;

    // ── Build reusable filter clauses ──
    const where: Prisma.SurveyResponseWhereInput = {};
    if (tenantId) {
      where.tenantId = tenantId;
    }
    if (userRole === 'head_of_department' && userDept) {
      where.department = userDept;
    } else if (department && department !== 'all') {
      where.department = department as string;
    }

    const dateFilter: Prisma.SurveyResponseWhereInput['submittedAt'] = {};
    let hasDateFilter = false;
    if (startDate) {
      dateFilter.gte = new Date(startDate as string);
      hasDateFilter = true;
    }
    if (endDate) {
      const end = new Date(endDate as string);
      end.setHours(23, 59, 59, 999);
      dateFilter.lte = end;
      hasDateFilter = true;
    }
    if (hasDateFilter) where.submittedAt = dateFilter;

    // Build SQL conditions for raw queries
    const conds: Prisma.Sql[] = [];
    if (tenantId) {
      conds.push(Prisma.sql`tenantId = ${tenantId}`);
    }
    if (userRole === 'head_of_department' && userDept) {
      conds.push(Prisma.sql`department = ${userDept}`);
    } else if (department && department !== 'all') {
      conds.push(Prisma.sql`department = ${department as string}`);
    }
    if (startDate) {
      conds.push(Prisma.sql`submittedAt >= ${new Date(startDate as string)}`);
    }
    if (endDate) {
      const end = new Date(endDate as string);
      end.setHours(23, 59, 59, 999);
      conds.push(Prisma.sql`submittedAt <= ${end}`);
    }

    const sqlWhere = conds.length > 0 ? Prisma.sql`WHERE ${Prisma.join(conds, ' AND ')}` : Prisma.empty;

    // ── 1. Totals & Average ──
    const totals = await prisma.surveyResponse.aggregate({
      where,
      _count: { id: true },
      _avg: { overallScore: true },
    });
    const totalResponses = totals._count.id;
    const averageScore = Math.round(totals._avg.overallScore ?? 0);

    // ── 2. Previous period stats (average + response rate) ──
    let previousAverageScore = 0;
    let previousCount = 0;
    let responseRate = 100;
    let previousResponseRate = 100;
    let midpoint: Date | null = null;
    if (totalResponses > 0) {
      const timeBounds = await prisma.surveyResponse.aggregate({
        where,
        _min: { submittedAt: true },
        _max: { submittedAt: true }
      });
      const minDate = timeBounds._min.submittedAt || new Date();
      const maxDate = timeBounds._max.submittedAt || new Date();
      const timeSpan = maxDate.getTime() - minDate.getTime();
      
      if (timeSpan > 0) {
        midpoint = new Date(minDate.getTime() + timeSpan / 2);
        const prevStats = await prisma.surveyResponse.aggregate({
          where: {
            ...where,
            submittedAt: { ...(where.submittedAt as Prisma.DateTimeFilter | undefined), lt: midpoint }
          },
          _avg: { overallScore: true },
          _count: { id: true },
        });
        previousAverageScore = Math.round(prevStats._avg.overallScore ?? 0);
        previousCount = prevStats._count.id;
        const newerCount = totalResponses - previousCount;
        responseRate = previousCount > 0 ? Math.round((newerCount / previousCount) * 100) : 100;
        previousResponseRate = 100;
      } else {
        previousAverageScore = averageScore;
      }
    }

    // ── 3. NPS Score ──
    const npsQuestionIds = await npsService.getNpsQuestionIds();
    const { score: npsScore } = await npsService.calculateNPS(conds, npsQuestionIds);

    let previousNpsScore = 0;
    if (npsQuestionIds.length > 0 && totalResponses > 0 && midpoint) {
      const prevConds = [...conds, Prisma.sql`submittedAt < ${midpoint}`];
      const { score: prevNps } = await npsService.calculateNPS(prevConds, npsQuestionIds);
      previousNpsScore = prevNps;
    }

    // ── 4. Department Scores (always all depts so Hall of Fame ranking is real) ──
    const deptWhere: Prisma.SurveyResponseWhereInput = {};
    if (tenantId) {
      deptWhere.tenantId = tenantId;
    }
    if (hasDateFilter) deptWhere.submittedAt = dateFilter;

    const deptGroups = await prisma.surveyResponse.groupBy({
      by: ['department'],
      where: deptWhere,
      _avg: { overallScore: true },
      _count: { id: true },
    });
    const departmentScores = deptGroups
      .map(d => ({
        name: d.department,
        score: Math.round(d._avg.overallScore ?? 0),
        count: d._count.id,
      }))
      .sort((a, b) => b.score - a.score);

    // ── 5. Hourly Stats ──
    const hourlyRaw = await prisma.$queryRaw<{h: number; avg_score: number | null; cnt: bigint}[]>`
      SELECT HOUR(submittedAt) as h, AVG(overallScore) as avg_score, COUNT(*) as cnt
      FROM survey_responses
      ${sqlWhere}
      GROUP BY HOUR(submittedAt)
      ORDER BY h
    `;
    const hourlyStats = Array.from({ length: 24 }, (_, i) => {
      const r = hourlyRaw.find(raw => raw.h === i);
      return {
        hour: `${i}:00`,
        score: Math.round(r?.avg_score ?? 0),
        count: Number(r?.cnt ?? 0)
      };
    });

    // ── 6. Day-of-Week Stats ──
    const daysAr = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const dayRaw = await prisma.$queryRaw<{d: number; avg_score: number | null; cnt: bigint}[]>`
      SELECT DAYOFWEEK(submittedAt) as d, AVG(overallScore) as avg_score, COUNT(*) as cnt
      FROM survey_responses
      ${sqlWhere}
      GROUP BY DAYOFWEEK(submittedAt)
      ORDER BY d
    `;
    const dayStats = daysAr.map((label, i) => {
      const r = dayRaw.find(raw => raw.d === i + 1); // MySQL 1=Sunday
      return {
        day: label,
        score: Math.round(r?.avg_score ?? 0),
        count: Number(r?.cnt ?? 0)
      };
    });

    // ── 7. Category Scores (Dynamic) ──
    const categoryScores = await this.calculateCategoryScores(sqlWhere);

    // ── 8. Weekly Trend Data (last 12 weeks) ──
    const trendData: { date: string; score: number; count: number }[] = [];
    try {
      const twelveWeeksAgo = new Date();
      twelveWeeksAgo.setDate(twelveWeeksAgo.getDate() - 84);
      const trendWhere: Prisma.SurveyResponseWhereInput = { ...where };
      if (trendWhere.submittedAt) {
        trendWhere.submittedAt = {
          ...(trendWhere.submittedAt as Prisma.DateTimeFilter | undefined),
          gte: twelveWeeksAgo,
        };
      } else {
        trendWhere.submittedAt = { gte: twelveWeeksAgo };
      }
      const allRecent = await prisma.surveyResponse.findMany({
        where: trendWhere,
        select: { overallScore: true, submittedAt: true },
      });
      const now = new Date();
      for (let i = 11; i >= 0; i--) {
        const weekStart = new Date(now);
        weekStart.setDate(weekStart.getDate() - i * 7);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 7);
        const weekLabel = `${weekStart.getDate()}/${weekStart.getMonth() + 1}`;
        const weekResponses = allRecent.filter(r => {
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
    } catch {
      // trendData stays empty array on error
    }

    // ── 9. Satisfaction Distribution ──
    const [distResult] = await prisma.$queryRaw<{excellent: bigint; good: bigint; average: bigint; poor: bigint}[]>`
      SELECT
        SUM(CASE WHEN overallScore >= 85 THEN 1 ELSE 0 END) as excellent,
        SUM(CASE WHEN overallScore >= 70 AND overallScore < 85 THEN 1 ELSE 0 END) as good,
        SUM(CASE WHEN overallScore >= 50 AND overallScore < 70 THEN 1 ELSE 0 END) as average,
        SUM(CASE WHEN overallScore < 50 THEN 1 ELSE 0 END) as poor
      FROM survey_responses
      ${sqlWhere}
    `;

    return {
      totalResponses,
      averageScore,
      previousAverageScore,
      npsScore,
      previousNpsScore,
      departmentScores,
      hourlyStats,
      dayStats,
      categoryScores,
      trendData,
      satisfactionDistribution: [
        { level: 'ممتاز', count: Number(distResult?.excellent ?? 0), color: '#10B981' },
        { level: 'جيد', count: Number(distResult?.good ?? 0), color: '#3B82F6' },
        { level: 'متوسط', count: Number(distResult?.average ?? 0), color: '#F59E0B' },
        { level: 'ضعيف', count: Number(distResult?.poor ?? 0), color: '#EF4444' },
      ],
      responseRate,
      previousResponseRate
    };
  },

  async calculateCategoryScores(sqlWhere: Prisma.Sql) {
    const allQuestions = await prisma.surveyQuestion.findMany({
      include: { section: true },
    });

    const categoryMap = new Map<string, string[]>();
    for (const q of allQuestions) {
      if (q.type !== 'stars' && q.type !== 'emoji' && q.type !== 'yes_no') continue;
      const catName = q.category || q.section.title;
      if (!catName) continue;
      if (!categoryMap.has(catName)) categoryMap.set(catName, []);
      categoryMap.get(catName)!.push(q.id);
    }

    if (categoryMap.size === 0) return [];

    const categoryEntries = Array.from(categoryMap.entries());
    const selectFields: string[] = [];
    
    categoryEntries.forEach(([_, keys], idx) => {
      const safeKeys = keys.filter(k => /^[a-zA-Z0-9_\-]+$/.test(k));
      if (safeKeys.length === 0) return;

      const sumParts = safeKeys.map(k => {
        const isYesNo = allQuestions.find(q => q.id === k)?.type === 'yes_no';
        const valExpr = `JSON_EXTRACT(answers, '$.${k}')`;
        return `COALESCE(SUM(CASE WHEN ${valExpr} IS NOT NULL THEN CAST(JSON_UNQUOTE(${valExpr}) AS UNSIGNED) * ${isYesNo ? 5 : 1} ELSE 0 END), 0)`;
      });
      const countParts = safeKeys.map(k => `SUM(CASE WHEN JSON_EXTRACT(answers, '$.${k}') IS NOT NULL THEN 1 ELSE 0 END)`);

      selectFields.push(`(${sumParts.join(' + ')}) as sum_${idx}`);
      selectFields.push(`(${countParts.join(' + ')}) as count_${idx}`);
    });

    if (selectFields.length === 0) return [];

    const [combinedResults] = await prisma.$queryRaw<Record<string, number | null>[]>`
      SELECT ${Prisma.raw(selectFields.join(', '))}
      FROM survey_responses
      ${sqlWhere}
    `;

    return categoryEntries.map(([catName], idx) => {
      const totalSum = Number(combinedResults?.[`sum_${idx}`] ?? 0);
      const totalCount = Number(combinedResults?.[`count_${idx}`] ?? 0);
      return {
        category: catName,
        score: totalCount > 0 ? Math.round((totalSum / (totalCount * 5)) * 100) : 0,
      };
    });
  },

  /**
   * Predictive analysis for Early Warning System.
   * Performs the heavy statistical trend analysis on the server instead of the browser.
   */
  async getPredictiveStats(tenantId?: string | null) {
    const where: Prisma.SurveyResponseWhereInput = {};
    if (tenantId) where.tenantId = tenantId;

    const allResponses = await prisma.surveyResponse.findMany({
      where,
      select: { department: true, submittedAt: true, overallScore: true, answers: true },
      orderBy: { submittedAt: 'asc' },
    });

    const allQuestions = await prisma.surveyQuestion.findMany({
      include: { section: true },
    });

    const questionToCategoryMap = new Map<string, string>();
    const allCategories = new Set<string>();

    for (const q of allQuestions) {
      if (q.type !== 'stars' && q.type !== 'emoji' && q.type !== 'yes_no') continue;
      const catName = q.category || q.section.title;
      if (!catName) continue;
      questionToCategoryMap.set(q.id, catName);
      allCategories.add(catName);
    }

    const deptGroups: Record<string, typeof allResponses> = {};
    allResponses.forEach(r => {
      const dept = r.department;
      if (!deptGroups[dept]) deptGroups[dept] = [];
      deptGroups[dept].push(r);
    });

    const alerts: Array<{
      id: string; department: string; previousAvg: number; currentAvg: number;
      predictedScore: number; drop: number; dropPercentage: number;
      keyDriver: string; sampleCount: number; lastResponseDate: string;
    }> = [];

    let sumOfAverages = 0;
    let deptCount = 0;

    Object.entries(deptGroups).forEach(([dept, sorted]) => {
      const deptAvg = sorted.reduce((sum, r) => sum + r.overallScore, 0) / sorted.length;
      sumOfAverages += deptAvg;
      deptCount++;

      if (sorted.length < 6) return;

      const halfSize = Math.min(10, Math.floor(sorted.length / 2));
      const currentPeriod = sorted.slice(-halfSize);
      const previousPeriod = sorted.slice(-2 * halfSize, -halfSize);

      if (currentPeriod.length === 0 || previousPeriod.length === 0) return;

      const currentAvg = currentPeriod.reduce((sum, r) => sum + r.overallScore, 0) / currentPeriod.length;
      const previousAvg = previousPeriod.reduce((sum, r) => sum + r.overallScore, 0) / previousPeriod.length;

      const drop = previousAvg - currentAvg;
      const dropPercentage = previousAvg > 0 ? (drop / previousAvg) * 100 : 0;

      if (drop >= 8) {
        const predictedScore = Math.max(0, Math.min(100, Math.round(currentAvg - drop * 0.7)));

        const currentCategoryScores: Record<string, number> = {};
        const previousCategoryScores: Record<string, number> = {};

        const countCategoryAnswers = (period: typeof sorted, scores: Record<string, number>) => {
          const categorySums: Record<string, number> = {};
          const categoryCounts: Record<string, number> = {};

          period.forEach(r => {
            const answers = r.answers as Record<string, unknown> || {};
            Object.entries(answers).forEach(([qId, val]) => {
              if (typeof val === 'number') {
                let cat = questionToCategoryMap.get(qId);
                if (!cat) {
                  // Fallback for legacy hardcoded q1..q11 IDs if they are not in the db
                  if (['q1', 'q2', 'q3'].includes(qId)) cat = 'Reception';
                  else if (['q4', 'q5', 'q6', 'q7'].includes(qId)) cat = 'Medical';
                  else if (['q8', 'q9', 'q10'].includes(qId)) cat = 'Facilities';
                  else if (qId === 'q11') cat = 'Pharmacy';
                }

                if (cat) {
                  const isYesNo = allQuestions.find(q => q.id === qId)?.type === 'yes_no';
                  const adjustedVal = isYesNo ? val * 5 : val;
                  categorySums[cat] = (categorySums[cat] || 0) + adjustedVal;
                  categoryCounts[cat] = (categoryCounts[cat] || 0) + 1;
                }
              }
            });
          });

          // Compute average score normalized to 20 or 100
          allCategories.forEach(cat => {
            const sum = categorySums[cat] || 0;
            const count = categoryCounts[cat] || 0;
            scores[cat] = count > 0 ? (sum / count) * 20 : 0;
          });
          // Also include the legacy categories in case they were not created in allQuestions
          ['Reception', 'Medical', 'Facilities', 'Pharmacy'].forEach(cat => {
            if (!(cat in scores)) {
              const sum = categorySums[cat] || 0;
              const count = categoryCounts[cat] || 0;
              scores[cat] = count > 0 ? (sum / count) * 20 : 0;
            }
          });
        };

        countCategoryAnswers(currentPeriod, currentCategoryScores);
        countCategoryAnswers(previousPeriod, previousCategoryScores);

        let worstCategory = '';
        let maxCatDrop = 0;
        Object.keys(currentCategoryScores).forEach(catKey => {
          const catDrop = (previousCategoryScores[catKey] || 0) - (currentCategoryScores[catKey] || 0);
          if (catDrop > maxCatDrop) {
            maxCatDrop = catDrop;
            worstCategory = catKey;
          }
        });

        const catTranslations: Record<string, string> = {
          Reception: 'الاستقبال والانتظار',
          Medical: 'الرعاية والخدمة الطبية',
          Facilities: 'المرافق والنظافة',
          Pharmacy: 'الصيدلية وصرف الدواء',
        };

        const keyDriver = worstCategory ? (catTranslations[worstCategory] || worstCategory) : 'التقييم العام للعيادات';

        alerts.push({
          id: `predictive-${dept}`,
          department: dept,
          previousAvg: Math.round(previousAvg),
          currentAvg: Math.round(currentAvg),
          predictedScore,
          drop: Math.round(drop),
          dropPercentage: Math.round(dropPercentage),
          keyDriver,
          sampleCount: currentPeriod.length,
          lastResponseDate: sorted[sorted.length - 1].submittedAt.toISOString(),
        });
      }
    });

    const averageHealthIndex = deptCount > 0 ? Math.round(sumOfAverages / deptCount) : 100;

    return {
      alerts,
      stats: {
        totalDepts: deptCount,
        activeWarnings: alerts.length,
        healthIndex: averageHealthIndex,
        totalResponsesAnalyzed: allResponses.length,
      },
    };
  }
};
