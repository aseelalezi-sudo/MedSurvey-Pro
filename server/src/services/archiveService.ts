import cron from 'node-cron';
import { prisma } from '../lib/prisma.js';
import { Prisma } from '@prisma/client';
import { createLogger } from '../lib/logger.js';

const logger = createLogger('ArchiveService');

/**
 * Core archiving logic. Computes date of 3 years ago and safely moves data inside a transaction.
 */
export async function archiveOldData(): Promise<void> {
  const threeYearsAgo = new Date();
  threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);

  logger.info(`Starting data archiving process. Threshold date: ${threeYearsAgo.toISOString()}`);

  try {
    const BATCH_SIZE = 500;

    // 1. ARCHIVE SURVEY RESPONSES (in batches)
    let responseTotal = 0;
    let responseSkip = 0;
    let responseBatch: any[];
    do {
      responseBatch = await prisma.surveyResponse.findMany({
        where: { submittedAt: { lt: threeYearsAgo } },
        take: BATCH_SIZE,
        skip: responseSkip,
      });
      if (responseBatch.length > 0) {
        await prisma.$transaction(async (tx: Prisma.TransactionClient) => {
          await tx.archivedSurveyResponse.createMany({
            data: responseBatch.map((r) => ({
              id: r.id,
              surveyId: r.surveyId,
              answers: r.answers || {},
              patientName: r.patientName,
              patientPhone: r.patientPhone,
              ageGroup: r.ageGroup,
              gender: r.gender,
              visitType: r.visitType,
              department: r.department,
              overallScore: r.overallScore,
              submittedAt: r.submittedAt,
            })),
            skipDuplicates: true,
          });
          await tx.surveyResponse.deleteMany({
            where: { id: { in: responseBatch.map((r) => r.id) } },
          });
        });
        responseTotal += responseBatch.length;
        responseSkip += responseBatch.length;
      }
    } while (responseBatch.length === BATCH_SIZE);
    if (responseTotal > 0) logger.info(`Archived and deleted ${responseTotal} survey responses.`);

    // 2. ARCHIVE AUDIT LOGS (in batches)
    let auditTotal = 0;
    let auditSkip = 0;
    let auditBatch: any[];
    do {
      auditBatch = await prisma.auditLog.findMany({
        where: { timestamp: { lt: threeYearsAgo } },
        take: BATCH_SIZE,
        skip: auditSkip,
      });
      if (auditBatch.length > 0) {
        await prisma.$transaction(async (tx: Prisma.TransactionClient) => {
          await tx.archivedAuditLog.createMany({
            data: auditBatch.map((l) => ({
              id: l.id,
              userId: l.userId,
              action: l.action,
              details: l.details,
              timestamp: l.timestamp,
            })),
            skipDuplicates: true,
          });
          await tx.auditLog.deleteMany({
            where: { id: { in: auditBatch.map((l) => l.id) } },
          });
        });
        auditTotal += auditBatch.length;
        auditSkip += auditBatch.length;
      }
    } while (auditBatch.length === BATCH_SIZE);
    if (auditTotal > 0) logger.info(`Archived and deleted ${auditTotal} audit logs.`);

    logger.info('Data archiving process completed successfully.');
  } catch (error) {
    logger.error('Failed to execute data archiving process:', error);
  }
}

/**
 * Initializes and schedules the archiving cron job
 */
export function initArchiveScheduler(): void {
  logger.info('Initializing data archiving scheduler...');

  // Run immediately on boot to clear any leftover old records
  archiveOldData().catch((err) => {
    logger.error('Error in initial data archiving execution:', err);
  });

  // Schedule to run daily at 2:00 AM
  // Pattern: 'minute hour day-of-month month day-of-week'
  cron.schedule('0 2 * * *', () => {
    logger.info('Triggering scheduled daily archiving task...');
    archiveOldData().catch((err) => {
      logger.error('Error in scheduled data archiving execution:', err);
    });
  });

  logger.info('Data archiving task scheduled to run daily at 2:00 AM.');
}
