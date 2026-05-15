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
    // 1. ARCHIVE SURVEY RESPONSES
    const oldResponses = await prisma.surveyResponse.findMany({
      where: {
        submittedAt: {
          lt: threeYearsAgo,
        },
      },
    });

    if (oldResponses.length > 0) {
      logger.info(`Found ${oldResponses.length} survey responses to archive.`);

      // We use a transaction to ensure we don't delete unless we've successfully copied
      await prisma.$transaction(async (tx: Prisma.TransactionClient) => {
        // Copy to archive
        await tx.archivedSurveyResponse.createMany({
          data: oldResponses.map((r) => ({
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

        // Delete from active table
        await tx.surveyResponse.deleteMany({
          where: {
            id: {
              in: oldResponses.map((r) => r.id),
            },
          },
        });
      });

      logger.info(`Successfully archived and deleted ${oldResponses.length} survey responses.`);
    } else {
      logger.info('No survey responses found matching archiving threshold.');
    }

    // 2. ARCHIVE AUDIT LOGS
    const oldAuditLogs = await prisma.auditLog.findMany({
      where: {
        timestamp: {
          lt: threeYearsAgo,
        },
      },
    });

    if (oldAuditLogs.length > 0) {
      logger.info(`Found ${oldAuditLogs.length} audit logs to archive.`);

      await prisma.$transaction(async (tx: Prisma.TransactionClient) => {
        // Copy to archive
        await tx.archivedAuditLog.createMany({
          data: oldAuditLogs.map((l) => ({
            id: l.id,
            userId: l.userId,
            action: l.action,
            details: l.details,
            timestamp: l.timestamp,
          })),
          skipDuplicates: true,
        });

        // Delete from active table
        await tx.auditLog.deleteMany({
          where: {
            id: {
              in: oldAuditLogs.map((l) => l.id),
            },
          },
        });
      });

      logger.info(`Successfully archived and deleted ${oldAuditLogs.length} audit logs.`);
    } else {
      logger.info('No audit logs found matching archiving threshold.');
    }

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
