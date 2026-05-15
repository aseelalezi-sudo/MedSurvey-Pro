import { prisma } from './src/lib/prisma.js';
async function main() {
  const users = await prisma.user.findMany();
  console.log('USERS:', users.map(u => ({ username: u.username, dept: u.department, role: u.role })));
  
  const responses = await prisma.surveyResponse.groupBy({
    by: ['department'],
    _count: true
  });
  console.log('RESPONSES BY DEPT:', responses);
}
main().finally(() => prisma.$disconnect());
