import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function resetAdminPassword() {
  try {
    const hashedPassword = await bcrypt.hash('admin123', 12);
    
    await prisma.user.updateMany({
      where: { username: 'superadmin' },
      data: { password: hashedPassword },
    });
    
    console.log('Password for superadmin has been successfully updated to: admin123');
    process.exit(0);
  } catch (error) {
    console.error('Error updating password:', error);
    process.exit(1);
  }
}

resetAdminPassword();
