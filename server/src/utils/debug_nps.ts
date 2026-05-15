import { PrismaClient, Prisma } from '@prisma/client';
const prisma = new PrismaClient();

async function debug() {
  try {
    const responses = await prisma.surveyResponse.findMany({ take: 5 });
    console.log('Sample responses count:', responses.length);
    if (responses.length > 0) {
        console.log('Sample answers keys:', Object.keys(responses[0].answers as any));
    }
    
    const npsQuestions = await prisma.surveyQuestion.findMany({ where: { type: 'nps' } });
    console.log('NPS Question IDs in DB:', npsQuestions.map(q => q.id));
    
    // Check if any response has an answer for these IDs
    if (npsQuestions.length > 0) {
        const id = npsQuestions[0].id;
        const countWithNps = await prisma.surveyResponse.count({
            where: {
                answers: {
                    path: id,
                    not: Prisma.JsonNull
                }
            }
        });
        console.log(`Responses with answer for ${id}:`, countWithNps);
    }
  } catch (err) {
    console.error(err);
  } finally {
    await prisma.$disconnect();
  }
}
debug();
