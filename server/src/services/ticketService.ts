import { prisma } from '../lib/prisma.js';
import { createLogger } from '../lib/logger.js';

const logger = createLogger('TicketService');

export const ticketService = {
  /**
   * Automatically creates a support ticket if the survey score is critically low.
   */
  async createAutoTicket(responseId: string, overallScore: number, department: string, patientInfo?: any) {
    if (overallScore >= 50) return null;

    try {
      const ticket = await prisma.ticket.create({
        data: {
          responseId,
          department,
          patientName: patientInfo?.name || 'مجهول الهوية',
          patientPhone: patientInfo?.phone || null,
          priority: overallScore < 30 ? 'high' : 'medium',
          status: 'open',
          description: `تنبيه آلي: تقييم منخفض جداً (${overallScore}%). المراجع أبدى عدم رضاه عن الخدمة في قسم ${department}. يرجى المتابعة الفورية.`,
        },
      });
      
      logger.info(`Auto-ticket created for response ${responseId} due to low score (${overallScore}%)`);
      return ticket;
    } catch (error) {
      logger.error('Failed to create automatic ticket:', error);
      // Non-fatal error for the main response flow
      return null;
    }
  }
};
