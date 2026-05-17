import { z } from 'zod';

export const loginSchema = z.object({
  username: z.string().min(1, "اسم المستخدم مطلوب"),
  password: z.string().min(1, "كلمة المرور مطلوبة")
});

export const createUserSchema = z.object({
  username: z.string().min(3, "اسم المستخدم يجب أن يكون 3 أحرف على الأقل"),
  password: z.string().min(6, "كلمة المرور يجب أن تكون 6 أحرف على الأقل"),
  name: z.string().min(1, "الاسم مطلوب"),
  email: z.string().email("البريد الإلكتروني غير صالح").optional().or(z.literal('')),
  role: z.enum(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff']),
  department: z.string().optional().nullable(),
  isActive: z.boolean().default(true),
  avatar: z.string().optional()
});

export const updateUserSchema = z.object({
  password: z.string().min(6, "كلمة المرور يجب أن تكون 6 أحرف على الأقل").optional().or(z.literal('')),
  name: z.string().min(1, "الاسم مطلوب").optional(),
  email: z.string().email("البريد الإلكتروني غير صالح").optional().or(z.literal('')),
  role: z.enum(['super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff']).optional(),
  department: z.string().optional().nullable(),
  isActive: z.boolean().optional(),
  avatar: z.string().optional()
});

export const changeUserPasswordSchema = z.object({
  password: z.string().min(6, "كلمة المرور يجب أن تكون 6 أحرف على الأقل")
});

export const updateTicketSchema = z.object({
  status: z.enum(['open', 'in_progress', 'resolved']).optional(),
  resolutionNotes: z.string().optional(),
  assignedTo: z.string().optional()
});

const surveyQuestionSchema = z.object({
  type: z.enum(['rating', 'stars', 'emoji', 'text', 'multiple_choice', 'yes_no', 'nps']),
  title: z.string().min(1, "السؤال مطلوب"),
  description: z.string().optional().nullable(),
  required: z.boolean().default(false),
  category: z.string().optional().default(''),
  options: z.any().optional().nullable(),
  followUp: z.any().optional().nullable(),
  sortOrder: z.number().default(0)
});

const surveySectionSchema = z.object({
  title: z.string().min(1, "عنوان القسم مطلوب"),
  description: z.string().optional().nullable(),
  icon: z.string().default("clipboard-check"),
  sortOrder: z.number().default(0),
  questions: z.array(surveyQuestionSchema)
});

export const createSurveySchema = z.object({
  title: z.string().min(1, "عنوان الاستبيان مطلوب"),
  description: z.string().optional().nullable(),
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
  answers: z.record(z.any()),
  patientInfo: z.object({
    name: z.string().optional().nullable(),
    phone: z.string().optional().nullable(),
    ageGroup: z.string().optional().nullable(),
    gender: z.string().optional().nullable(),
    visitType: z.string().optional().nullable()
  }).optional().nullable(),
  department: z.string().min(1, "القسم مطلوب"),
  overallScore: z.number().min(0).max(100).optional()
});

export const updateSettingsSchema = z.record(z.any());
