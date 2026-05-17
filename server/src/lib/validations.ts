import { z } from 'zod';

export const loginSchema = z.object({
  username: z.string().min(1, "اسم المستخدم مطلوب").max(50),
  password: z.string().min(1, "كلمة المرور مطلوبة").max(128)
});

const passwordSchema = z.string().min(8, "كلمة المرور يجب أن تكون 8 أحرف على الأقل")
  .refine(val => /[a-zA-Z]/.test(val), "يجب أن تحتوي كلمة المرور على حرف لاتيني واحد على الأقل")
  .refine(val => /[0-9]/.test(val), "يجب أن تحتوي كلمة المرور على رقم واحد على الأقل");

const optionalPasswordSchema = z.string().min(8, "كلمة المرور يجب أن تكون 8 أحرف على الأقل")
  .refine(val => /[a-zA-Z]/.test(val), "يجب أن تحتوي كلمة المرور على حرف لاتيني واحد على الأقل")
  .refine(val => /[0-9]/.test(val), "يجب أن تحتوي كلمة المرور على رقم واحد على الأقل")
  .optional().or(z.literal(''));

export const createUserSchema = z.object({
  username: z.string().min(3, "اسم المستخدم يجب أن يكون 3 أحرف على الأقل").max(50, "اسم المستخدم طويل جداً"),
  password: passwordSchema,
  name: z.string().min(1, "الاسم مطلوب").max(100, "الاسم طويل جداً"),
  email: z.string().email("البريد الإلكتروني غير صالح").optional().or(z.literal('')),
  role: z.enum(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff']),
  department: z.string().max(100).optional().nullable(),
  isActive: z.boolean().default(true),
  avatar: z.string().optional()
});

export const updateUserSchema = z.object({
  password: optionalPasswordSchema,
  name: z.string().min(1, "الاسم مطلوب").max(100).optional(),
  email: z.string().email("البريد الإلكتروني غير صالح").optional().or(z.literal('')),
  role: z.enum(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff']).optional(),
  department: z.string().max(100).optional().nullable(),
  isActive: z.boolean().optional(),
  avatar: z.string().optional()
});

export const changeUserPasswordSchema = z.object({
  password: passwordSchema
});

export const updateTicketSchema = z.object({
  status: z.enum(['open', 'in_progress', 'resolved']).optional(),
  resolutionNotes: z.string().optional(),
  assignedTo: z.string().optional()
});

const surveyQuestionSchema = z.object({
  type: z.enum(['rating', 'stars', 'emoji', 'text', 'multiple_choice', 'yes_no', 'nps']),
  title: z.string().min(1, "السؤال مطلوب").max(500),
  description: z.string().max(2000).optional().nullable(),
  required: z.boolean().default(false),
  category: z.string().max(200).optional().default(''),
  options: z.any().optional().nullable(),
  followUp: z.any().optional().nullable(),
  sortOrder: z.number().default(0)
});

const surveySectionSchema = z.object({
  title: z.string().min(1, "عنوان القسم مطلوب").max(200),
  description: z.string().max(2000).optional().nullable(),
  icon: z.string().max(50).default("clipboard-check"),
  sortOrder: z.number().default(0),
  questions: z.array(surveyQuestionSchema)
});

export const createSurveySchema = z.object({
  title: z.string().min(1, "عنوان الاستبيان مطلوب").max(200),
  description: z.string().max(5000).optional().nullable(),
  isActive: z.boolean().default(true),
  requireName: z.boolean().default(false),
  requirePhone: z.boolean().default(false),
  assignedDepartments: z.any().optional().nullable(),
  tips: z.any().optional().nullable(),
  sections: z.array(surveySectionSchema)
});

export const updateSurveySchema = createSurveySchema;

export const submitResponseSchema = z.object({
  surveyId: z.string().min(1, "رقم الاستبيان مطلوب"),
  answers: z.record(z.union([z.string(), z.number(), z.boolean(), z.null(), z.array(z.any())])),
  patientInfo: z.object({
    name: z.string().max(200).optional().nullable(),
    phone: z.string().max(20).optional().nullable(),
    ageGroup: z.string().max(100).optional().nullable(),
    gender: z.string().max(50).optional().nullable(),
    visitType: z.string().max(100).optional().nullable()
  }).optional().nullable(),
  department: z.string().min(1, "القسم مطلوب").max(100),
  overallScore: z.number().min(0).max(100).optional()
});

export const updateSettingsSchema = z.object({
  hospital: z.object({
    name: z.string().optional(),
    shortName: z.string().optional(),
    logo: z.string().optional(),
    address: z.string().optional(),
    phone: z.string().optional(),
    email: z.string().optional(),
    website: z.string().optional(),
    description: z.string().optional(),
    workingHours: z.string().optional(),
    operatingTitle: z.string().optional(),
  }).optional(),
  departments: z.array(z.object({
    id: z.string(),
    name: z.string(),
    isActive: z.boolean().optional(),
    color: z.string().optional(),
  })).optional(),
  ageGroups: z.array(z.object({
    id: z.string(),
    label: z.string(),
  })).optional(),
  visitTypes: z.array(z.object({
    id: z.string(),
    label: z.string(),
  })).optional(),
  surveySettings: z.object({
    allowAnonymous: z.boolean().optional(),
    requireAllQuestions: z.boolean().optional(),
    requireName: z.boolean().optional(),
    requirePhone: z.boolean().optional(),
    showProgressBar: z.boolean().optional(),
    enableThankYouPage: z.boolean().optional(),
    thankYouMessage: z.string().optional(),
  }).optional(),
  appearance: z.object({
    primaryColor: z.string().optional(),
    secondaryColor: z.string().optional(),
    fontFamily: z.string().optional(),
  }).optional(),
}).partial().passthrough();
