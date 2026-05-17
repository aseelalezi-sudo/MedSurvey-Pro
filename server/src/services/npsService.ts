import { Prisma } from '@prisma/client';
import { prisma } from '../lib/prisma.js';

export const npsService = {
  /**
   * Calculates the Net Promoter Score (NPS) for a given set of conditions.
   * NPS = (% Promoters - % Detractors) * 100
   */
  async calculateNPS(conditions: Prisma.Sql[], npsQuestionIds: string[]) {
    if (npsQuestionIds.length === 0) return { score: 0, total: 0 };

    // Build a COALESCE chain to pick the first non-null NPS answer from any NPS question
    const safeIds = npsQuestionIds.filter(id => /^[a-zA-Z0-9_\-]+$/.test(id));
    if (safeIds.length === 0) return { score: 0, total: 0 };

    const coalesceExpr = safeIds
      .map(id => `JSON_EXTRACT(answers, '$.${id}')`)
      .join(', ');
    
    const numericChecks = safeIds
      .map(id => `JSON_EXTRACT(answers, '$.${id}') IS NOT NULL`)
      .join(' OR ');

    const npsConds = [...conditions];
    npsConds.push(Prisma.sql`(${Prisma.raw(numericChecks)})`);
    const npsWhere = npsConds.length > 0 
      ? Prisma.sql`WHERE ${Prisma.join(npsConds, ' AND ')}` 
      : Prisma.empty;

    const npsResult = await prisma.$queryRaw<{
      total: bigint;
      promoters: bigint;
      detractors: bigint;
    }[]>`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN CAST(JSON_UNQUOTE(COALESCE(${Prisma.raw(coalesceExpr)})) AS SIGNED) >= 9 THEN 1 ELSE 0 END) as promoters,
        SUM(CASE WHEN CAST(JSON_UNQUOTE(COALESCE(${Prisma.raw(coalesceExpr)})) AS SIGNED) <= 6 THEN 1 ELSE 0 END) as detractors
      FROM survey_responses
      ${npsWhere}
    `;

    const total = Number(npsResult?.[0]?.total ?? 0);
    const promoters = Number(npsResult?.[0]?.promoters ?? 0);
    const detractors = Number(npsResult?.[0]?.detractors ?? 0);

    const score = total > 0
      ? Math.round(((promoters - detractors) / total) * 100)
      : 0;

    return { score, total };
  },

  /**
   * Fetches all question IDs of type 'nps'
   */
  async getNpsQuestionIds() {
    const questions = await prisma.surveyQuestion.findMany({
      where: { type: 'nps' },
      select: { id: true }
    });
    return questions.map(q => q.id);
  }
};
