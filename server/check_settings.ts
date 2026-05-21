import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

async function checkSettings() {
  try {
    const count = await prisma.settings.count();
    console.log(`Total settings in DB: ${count}`);
    const settings = await prisma.settings.findMany();
    console.log(JSON.stringify(settings, null, 2));
    process.exit(0);
  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

checkSettings();
