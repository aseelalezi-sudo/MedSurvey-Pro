import { PrismaClient } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

const PASSWORD_ROUNDS = 10;

async function hashPassword(password: string) {
  return bcrypt.hash(password, PASSWORD_ROUNDS);
}

async function main() {
  console.log('🌱 Starting database seed...');

  const tenant =
    (await prisma.tenant.findFirst({
      where: { name: 'Default Hospital' },
    })) ??
    (await prisma.tenant.create({
      data: {
        name: 'Default Hospital',
      },
    }));

  await prisma.settings.upsert({
    where: {
      tenantId: tenant.id,
    },
    update: {
      data: {
        hospitalName: 'MedSurvey Pro Hospital',
        language: 'ar',
        theme: 'default',
      },
    },
    create: {
      tenantId: tenant.id,
      data: {
        hospitalName: 'MedSurvey Pro Hospital',
        language: 'ar',
        theme: 'default',
      },
    },
  });

  const users = [
    {
      username: 'superadmin',
      password: process.env.SEED_SUPERADMIN_PW || 'superadmin123',
      name: 'Super Admin',
      email: 'superadmin@medsurvey.local',
      role: 'super_admin' as const,
      department: null,
    },
    {
      username: 'admin',
      password: process.env.SEED_ADMIN_PW || 'admin123',
      name: 'System Admin',
      email: 'admin@medsurvey.local',
      role: 'admin' as const,
      department: null,
    },
    {
      username: 'head',
      password: process.env.SEED_HEAD_PW || 'head123',
      name: 'Head of Department',
      email: 'head@medsurvey.local',
      role: 'head_of_department' as const,
      department: 'الطوارئ',
    },
    {
      username: 'staff',
      password: process.env.SEED_STAFF_PW || 'staff123',
      name: 'Staff User',
      email: 'staff@medsurvey.local',
      role: 'staff' as const,
      department: 'الطوارئ',
    },
  ];

  for (const user of users) {
    const password = await hashPassword(user.password);

    await prisma.user.upsert({
      where: {
        username: user.username,
      },
      update: {
        password,
        name: user.name,
        email: user.email,
        role: user.role,
        department: user.department,
        isActive: true,
        tenantId: tenant.id,
      },
      create: {
        username: user.username,
        password,
        name: user.name,
        email: user.email,
        role: user.role,
        department: user.department,
        isActive: true,
        tenantId: tenant.id,
      },
    });
  }

  const existingSurvey = await prisma.survey.findFirst({
    where: {
      title: 'استبيان رضا المرضى',
      tenantId: tenant.id,
    },
  });

  if (!existingSurvey) {
    await prisma.survey.create({
      data: {
        title: 'استبيان رضا المرضى',
        description: 'استبيان تجريبي لقياس رضا المرضى عن الخدمات الطبية.',
        isActive: true,
        requireName: false,
        requirePhone: false,
        assignedDepartments: ['الطوارئ', 'العيادات', 'المختبر', 'الأشعة'],
        tips: ['اختر التقييم الأقرب لتجربتك.', 'ملاحظاتك تساعدنا على تحسين جودة الخدمة.'],
        tenantId: tenant.id,
        sections: {
          create: [
            {
              title: 'الاستقبال',
              description: 'تقييم تجربة الاستقبال وسهولة الإجراءات.',
              icon: 'clipboard-check',
              sortOrder: 1,
              questions: {
                create: [
                  {
                    type: 'stars',
                    title: 'ما مدى رضاك عن سرعة إجراءات الاستقبال؟',
                    required: true,
                    category: 'reception',
                    sortOrder: 1,
                  },
                  {
                    type: 'stars',
                    title: 'ما مدى رضاك عن تعامل موظفي الاستقبال؟',
                    required: true,
                    category: 'reception',
                    sortOrder: 2,
                  },
                ],
              },
            },
            {
              title: 'الخدمة الطبية',
              description: 'تقييم جودة الخدمة الطبية المقدمة.',
              icon: 'heart-pulse',
              sortOrder: 2,
              questions: {
                create: [
                  {
                    type: 'stars',
                    title: 'ما مدى رضاك عن تعامل الكادر الطبي؟',
                    required: true,
                    category: 'medical',
                    sortOrder: 1,
                  },
                  {
                    type: 'nps',
                    title: 'ما احتمالية أن توصي بخدماتنا لشخص آخر؟',
                    required: true,
                    category: 'medical',
                    sortOrder: 2,
                  },
                ],
              },
            },
            {
              title: 'ملاحظات عامة',
              description: 'ملاحظات واقتراحات إضافية.',
              icon: 'message-square',
              sortOrder: 3,
              questions: {
                create: [
                  {
                    type: 'text',
                    title: 'هل لديك أي ملاحظات أو اقتراحات؟',
                    required: false,
                    category: 'general',
                    sortOrder: 1,
                  },
                ],
              },
            },
          ],
        },
      },
    });
  }

  console.log('✅ Database seed completed successfully.');
}

main()
  .catch((error) => {
    console.error('❌ Database seed failed:', error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
