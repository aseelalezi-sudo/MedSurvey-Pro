import bcrypt from 'bcryptjs';
import crypto from 'crypto';
import { prisma } from './prisma.js';
import { createLogger } from './logger.js';

const logger = createLogger('InitDB');

export async function ensureDefaultSuperAdmin() {
  try {
    const superAdminCount = await prisma.user.count({
      where: { role: 'super_admin' },
    });

    if (superAdminCount === 0) {
      const adminPassword = process.env.SUPER_ADMIN_PASSWORD || crypto.randomBytes(4).toString('hex');
      logger.info('No super_admin user found in database. Creating default super_admin account...');
      const hashedPassword = await bcrypt.hash(adminPassword, 12);
      await prisma.user.create({
        data: {
          username: 'superadmin',
          password: hashedPassword,
          name: 'المدير العام',
          email: 'superadmin@hospital.com',
          role: 'super_admin',
          isActive: true,
        },
      });
      logger.info(`✅ Default super_admin account created. Username: superadmin`);
      logger.info(`🔐 Password: ${adminPassword} — يرجى حفظها وتغييرها فوراً`);
    } else {
      logger.info(`Verified ${superAdminCount} super_admin user(s) exist in the database.`);
    }
  } catch (error) {
    logger.error('Failed to ensure default super_admin:', error);
  }
}
