import { prisma } from './prisma.js';
import { createLogger } from './logger.js';

const logger = createLogger('AuditLog');

type AuditDetails = {
  messageKey: string;
  params?: Record<string, string | number | boolean | null | undefined>;
};

export async function writeAuditLog(
  userId: string | undefined | null,
  action: string,
  details: AuditDetails
): Promise<void> {
  if (!userId) return;

  try {
    await prisma.auditLog.create({
      data: {
        userId,
        action,
        details: JSON.stringify(details),
      },
    });
  } catch (error) {
    logger.warn('Audit log write failed (non-fatal)', error);
  }
}
