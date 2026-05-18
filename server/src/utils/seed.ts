import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';
import crypto from 'crypto';
import { ticketService } from '../services/ticketService.js';

const prisma = new PrismaClient();

// Seed passwords from env vars with random fallbacks
const SEED_SUPERADMIN_PW = process.env.SEED_SUPERADMIN_PW || crypto.randomBytes(4).toString('hex');
const SEED_ADMIN_PW = process.env.SEED_ADMIN_PW || crypto.randomBytes(4).toString('hex');
const SEED_HEAD_PW = process.env.SEED_HEAD_PW || crypto.randomBytes(4).toString('hex');
const SEED_STAFF_PW = process.env.SEED_STAFF_PW || crypto.randomBytes(4).toString('hex');

async function seed() {
  console.log('🌱 بدء تهيئة قاعدة البيانات...\n');
  
  // Clear existing tickets and responses
  await prisma.ticket.deleteMany({});
  await prisma.surveyAnswer.deleteMany({});
  await prisma.surveyResponse.deleteMany({});

  // ============ 1. USERS ============
  console.log('👤 إنشاء المستخدمين...');

  const users = [
    {
      username: 'superadmin',
      password: await bcrypt.hash(SEED_SUPERADMIN_PW, 12),
      name: 'المدير العام',
      email: 'superadmin@hospital.com',
      role: 'super_admin' as const,
    },
    {
      username: 'admin',
      password: await bcrypt.hash(SEED_ADMIN_PW, 12),
      name: 'مدير النظام',
      email: 'admin@hospital.com',
      role: 'admin' as const,
    },
    {
      username: 'head',
      password: await bcrypt.hash(SEED_HEAD_PW, 12),
      name: 'د. أحمد محمد',
      email: 'head@hospital.com',
      role: 'head_of_department' as const,
      department: 'الباطنية',
    },
    {
      username: 'staff',
      password: await bcrypt.hash(SEED_STAFF_PW, 12),
      name: 'موظف الاستقبال',
      email: 'staff@hospital.com',
      role: 'staff' as const,
      department: 'الاستقبال',
    },
  ];

  for (const user of users) {
    await prisma.user.upsert({
      where: { username: user.username },
      update: {},
      create: user,
    });
  }
  console.log(`   ✅ تم إنشاء ${users.length} مستخدمين`);
  console.log(`      superadmin: ${SEED_SUPERADMIN_PW}`);
  console.log(`      admin: ${SEED_ADMIN_PW}`);
  console.log(`      head: ${SEED_HEAD_PW}`);
  console.log(`      staff: ${SEED_STAFF_PW}`);

  // ============ 2. DEFAULT SURVEY ============
  console.log('📋 إنشاء الاستبيان الافتراضي...');

  const existingSurvey = await prisma.survey.findFirst({ where: { title: 'استبيان رضا المرضى عن الخدمات الصحية' } });

  if (existingSurvey) {
    // Delete existing survey and its responses to start fresh with correct IDs
    await prisma.survey.delete({ where: { id: existingSurvey.id } });
    console.log('   🗑️ تم حذف الاستبيان القديم للبدء من جديد');
  }

  const survey = await prisma.survey.create({
    data: {
      id: 'default-survey-1',
      title: 'استبيان رضا المرضى عن الخدمات الصحية',
      description: 'نسعى لتقديم أفضل الخدمات الصحية لكم. رأيكم يهمنا ويساعدنا في التطوير المستمر.',
      isActive: true,
      requireName: false,
      requirePhone: false,
      sections: {
        create: [
          {
            id: 'sec-1',
            title: 'الاستقبال والتسجيل',
            description: 'تقييم تجربتكم عند الوصول والتسجيل',
            icon: 'door-open',
            sortOrder: 0,
            questions: {
              create: [
                { id: 'q1', type: 'stars', title: 'كيف تقيّم سرعة إجراءات الاستقبال والتسجيل؟', description: 'من 1 (ضعيف) إلى 5 (ممتاز)', required: true, category: 'الاستقبال', sortOrder: 0 },
                { id: 'q2', type: 'stars', title: 'كيف تقيّم تعامل موظفي الاستقبال معك؟', description: 'اللباقة، الاحترام، والمساعدة', required: true, category: 'الاستقبال', sortOrder: 1 },
                { id: 'q3', type: 'emoji', title: 'ما مدى رضاك عن وضوح الإرشادات والتوجيهات؟', required: true, category: 'الاستقبال', sortOrder: 2 },
              ],
            },
          },
          {
            id: 'sec-2',
            title: 'الرعاية الطبية',
            description: 'تقييم جودة الرعاية الطبية المقدمة',
            icon: 'stethoscope',
            sortOrder: 1,
            questions: {
              create: [
                { id: 'q4', type: 'stars', title: 'كيف تقيّم مستوى الاهتمام والرعاية من الطبيب المعالج؟', required: true, category: 'الرعاية الطبية', sortOrder: 0 },
                { id: 'q5', type: 'stars', title: 'هل قام الطبيب بشرح حالتك الصحية بشكل واضح؟', required: true, category: 'الرعاية الطبية', sortOrder: 1 },
                { id: 'q6', type: 'stars', title: 'كيف تقيّم مستوى الرعاية من طاقم التمريض؟', required: true, category: 'الرعاية الطبية', sortOrder: 2 },
                { id: 'q7', type: 'emoji', title: 'ما مدى رضاك عن وقت الانتظار للحصول على الخدمة الطبية؟', required: true, category: 'الرعاية الطبية', sortOrder: 3 },
              ],
            },
          },
          {
            id: 'sec-3',
            title: 'المرافق والنظافة',
            description: 'تقييم نظافة وجودة المرافق',
            icon: 'building',
            sortOrder: 2,
            questions: {
              create: [
                { id: 'q8', type: 'stars', title: 'كيف تقيّم نظافة المستشفى بشكل عام؟', required: true, category: 'المرافق', sortOrder: 0 },
                { id: 'q9', type: 'stars', title: 'كيف تقيّم جودة الغرف ومستوى الراحة فيها؟', required: false, category: 'المرافق', sortOrder: 1 },
                { id: 'q10', type: 'emoji', title: 'ما مدى رضاك عن جودة الوجبات الغذائية المقدمة؟', required: false, category: 'المرافق', sortOrder: 2 },
              ],
            },
          },
          {
            id: 'sec-4',
            title: 'الصيدلية والأدوية',
            description: 'تقييم خدمات الصيدلية',
            icon: 'pill',
            sortOrder: 3,
            questions: {
              create: [
                { id: 'q11', type: 'stars', title: 'كيف تقيّم سرعة صرف الأدوية من الصيدلية؟', required: true, category: 'الصيدلية', sortOrder: 0 },
                { id: 'q12', type: 'yes_no', title: 'هل تم شرح طريقة استخدام الأدوية بشكل واضح؟', required: true, category: 'الصيدلية', sortOrder: 1 },
              ],
            },
          },
          {
            id: 'sec-5',
            title: 'التقييم العام',
            description: 'رأيكم الشامل عن تجربتكم',
            icon: 'clipboard-check',
            sortOrder: 4,
            questions: {
              create: [
                { id: 'q13', type: 'nps', title: 'ما مدى احتمالية أن توصي عائلتك وأصدقائك بالتعامل مع مستشفانا؟', description: '0 = لن أوصي أبداً، 10 = سأوصي بالتأكيد', required: true, category: 'عام', sortOrder: 0 },
                {
                  id: 'q14',
                  type: 'multiple_choice', title: 'ما هو أكثر ما أعجبك في تجربتك؟', required: false, category: 'عام', sortOrder: 1,
                  options: [
                    { id: 'opt1', label: 'تعامل الطاقم الطبي', value: 'staff' },
                    { id: 'opt2', label: 'نظافة المرافق', value: 'cleanliness' },
                    { id: 'opt3', label: 'سرعة الخدمة', value: 'speed' },
                    { id: 'opt4', label: 'جودة العلاج', value: 'quality' },
                    { id: 'opt5', label: 'الأجهزة والمعدات الحديثة', value: 'equipment' },
                  ],
                },
                {
                  id: 'q15',
                  type: 'multiple_choice', title: 'ما هي الجوانب التي تحتاج إلى تحسين؟', required: false, category: 'عام', sortOrder: 2,
                  options: [
                    { id: 'imp1', label: 'وقت الانتظار', value: 'waiting' },
                    { id: 'imp2', label: 'التواصل مع المرضى', value: 'communication' },
                    { id: 'imp3', label: 'المواقف', value: 'parking' },
                    { id: 'imp4', label: 'خدمات الطعام', value: 'food' },
                    { id: 'imp5', label: 'النظافة', value: 'hygiene' },
                    { id: 'imp6', label: 'توفر المواعيد', value: 'appointments' },
                  ],
                },
                { id: 'q16', type: 'text', title: 'هل لديك أي ملاحظات أو اقتراحات إضافية؟', description: 'نرحب بجميع آرائكم واقتراحاتكم لتطوير خدماتنا', required: false, category: 'عام', sortOrder: 3 },
              ],
            },
          },
        ],
      },
    },
  });
  console.log('   ✅ تم إنشاء الاستبيان الافتراضي بالمعرفات الثابتة');

  // ============ 3. MOCK RESPONSES ============
  console.log('📊 إنشاء بيانات تجريبية...');

  const departments = ['الطوارئ', 'العيادات الخارجية', 'الباطنية', 'الجراحة', 'الأطفال', 'النساء والولادة', 'القلب'];
  const genders = ['ذكر', 'أنثى'];
  const ageGroups = ['أقل من 18 سنة', '18 - 30 سنة', '31 - 45 سنة', '46 - 60 سنة', 'أكثر من 60 سنة'];
  const visitTypes = ['زيارة طارئة', 'موعد مسبق', 'تنويم', 'مراجعة'];

  const mockData = [];
  for (let i = 0; i < 150; i++) {
    const dept = departments[Math.floor(Math.random() * departments.length)];
    // Create diverse scores: 70% positive, 30% mixed/negative
    const isPositive = Math.random() > 0.3;
    const baseScore = isPositive ? (4 + Math.random()) : (2 + Math.random() * 2);

    const answers: Record<string, string | number | boolean | null> = {};
    for (let q = 1; q <= 11; q++) {
      answers[`q${q}`] = Math.max(1, Math.min(5, Math.round(baseScore + (Math.random() - 0.5))));
    }
    answers['q12'] = Math.random() > 0.3 ? 'yes' : 'no';
    
    // NPS Logic: Promoters (9-10), Passives (7-8), Detractors (0-6)
    let npsVal;
    if (isPositive) {
        npsVal = Math.random() > 0.2 ? (9 + Math.floor(Math.random() * 2)) : (7 + Math.floor(Math.random() * 2));
    } else {
        npsVal = Math.floor(Math.random() * 7);
    }
    answers['q13'] = npsVal;

    answers['q14'] = ['staff', 'quality', 'speed'][Math.floor(Math.random() * 3)];
    answers['q15'] = ['waiting', 'communication', 'parking'][Math.floor(Math.random() * 3)];
    answers['q16'] = Math.random() > 0.7 ? 'شكراً على الخدمة الممتازة' : '';

    const daysAgo = Math.floor(Math.random() * 90);
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);

    mockData.push({
      surveyId: survey.id,
      answers,
      patientName: null,
      patientPhone: null,
      ageGroup: ageGroups[Math.floor(Math.random() * ageGroups.length)],
      gender: genders[Math.floor(Math.random() * genders.length)],
      visitType: visitTypes[Math.floor(Math.random() * visitTypes.length)],
      department: dept,
      overallScore: Math.round(baseScore * 20),
      submittedAt: date,
    });
  }

  for (const data of mockData) {
    const response = await prisma.surveyResponse.create({ data });
    // Manually trigger ticket creation since it's a seed
    if (data.overallScore < 50) {
        await ticketService.createAutoTicket(response.id, data.overallScore, data.department);
    }
  }
  console.log(`   ✅ تم إنشاء ${mockData.length} استجابة مع البلاغات المرتبطة`);

  // ============ 4. CRISIS SCENARIO (For Early Warning) ============
  console.log('⚠️ إنشاء سيناريو إنذار مبكر لقسم الطوارئ...');
  
  for (let i = 0; i < 20; i++) {
    const isRecent = i < 10;
    const daysAgo = isRecent ? Math.floor(Math.random() * 7) : (Math.floor(Math.random() * 10) + 30);
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);
    
    // Low scores (1-2) recently, high scores (4-5) previously
    const scoreVal = isRecent ? (1 + Math.random()) : (4 + Math.random());
    const answers: Record<string, number | string> = {};
    for (let q = 1; q <= 11; q++) answers[`q${q}`] = Math.round(scoreVal);
    answers['q12'] = isRecent ? 'no' : 'yes';
    answers['q13'] = isRecent ? 2 : 10;

    await prisma.surveyResponse.create({
      data: {
        surveyId: survey.id,
        answers,
        department: 'الطوارئ',
        overallScore: Math.round(scoreVal * 20),
        submittedAt: date,
      }
    });
  }
  console.log('   ✅ تم إنشاء سيناريو الإنذار المبكر بنجاح');

  // ============ 5. DEFAULT SETTINGS ============
  console.log('⚙️  إنشاء الإعدادات الافتراضية...');

  await prisma.settings.upsert({
    where: { id: 'global' },
    update: {},
    create: {
      id: 'global',
      tenantId: null,
      data: {
        hospital: {
          name: '',
          shortName: '',
          logo: '',
          address: '',
          phone: '',
          email: '',
          website: '',
          description: '',
          workingHours: '',
        },
        departments: [
          { id: 'dept-1', name: 'الطوارئ', isActive: true, color: '#EF4444' },
          { id: 'dept-2', name: 'العيادات الخارجية', isActive: true, color: '#3B82F6' },
          { id: 'dept-3', name: 'الباطنية', isActive: true, color: '#10B981' },
          { id: 'dept-4', name: 'الجراحة', isActive: true, color: '#8B5CF6' },
          { id: 'dept-5', name: 'الأطفال', isActive: true, color: '#F59E0B' },
          { id: 'dept-6', name: 'النساء والولادة', isActive: true, color: '#EC4899' },
          { id: 'dept-7', name: 'العظام', isActive: true, color: '#6366F1' },
          { id: 'dept-8', name: 'العيون', isActive: true, color: '#14B8A6' },
          { id: 'dept-9', name: 'الأنف والأذن والحنجرة', isActive: true, color: '#F97316' },
          { id: 'dept-10', name: 'الأسنان', isActive: true, color: '#06B6D4' },
          { id: 'dept-11', name: 'القلب', isActive: true, color: '#DC2626' },
          { id: 'dept-12', name: 'المختبر والأشعة', isActive: true, color: '#7C3AED' },
        ],
        ageGroups: [
          { id: 'age-1', label: 'أقل من 18 سنة' },
          { id: 'age-2', label: '18 - 30 سنة' },
          { id: 'age-3', label: '31 - 45 سنة' },
          { id: 'age-4', label: '46 - 60 سنة' },
          { id: 'age-5', label: 'أكثر من 60 سنة' },
        ],
        visitTypes: [
          { id: 'vt-1', label: 'زيارة طارئة' },
          { id: 'vt-2', label: 'موعد مسبق' },
          { id: 'vt-3', label: 'تنويم' },
          { id: 'vt-4', label: 'مراجعة' },
          { id: 'vt-5', label: 'عملية جراحية' },
        ],
        surveySettings: {
          allowAnonymous: true,
          requireAllQuestions: false,
          requireName: false,
          requirePhone: false,
          showProgressBar: true,
          enableThankYouPage: true,
          thankYouMessage: 'شكراً لمشاركتكم! رأيكم يساعدنا في تحسين خدماتنا.',
        },
        appearance: {
          primaryColor: '#0d9488',
          secondaryColor: '#10b981',
          fontFamily: 'Cairo',
        },
      },
    },
  });
  console.log('   ✅ تم إنشاء الإعدادات الافتراضية');

  console.log('\n✨ تمت تهيئة قاعدة البيانات بنجاح!\n');
}

seed()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
