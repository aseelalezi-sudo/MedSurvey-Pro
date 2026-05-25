export type QuestionType = 'rating' | 'stars' | 'emoji' | 'text' | 'multiple_choice' | 'yes_no' | 'nps';

export interface QuestionOption {
  id: string;
  label: string;
  value: string;
}

export type AnswerValue = string | number | boolean | string[] | null;

export interface SurveyQuestion {
  id: string;
  type: QuestionType;
  title: string;
  description?: string;
  required: boolean;
  options?: QuestionOption[];
  category: string;
  followUp?: {
    triggerValue: AnswerValue;
    condition: 'equals' | 'lessThan' | 'greaterThan';
    question: Omit<SurveyQuestion, 'followUp'>;
  };
}

export interface SurveySection {
  id: string;
  title: string;
  description: string;
  icon: string;
  questions: SurveyQuestion[];
}

export interface SurveyTemplate {
  id: string;
  title: string;
  description: string;
  sections: SurveySection[];
  createdAt: string;
  isActive: boolean;
  assignedDepartments?: string[];
  requireName?: boolean;
  requirePhone?: boolean;
  tips?: string[];
  responseCount?: number;
}

export interface SurveyResponse {
  id: string;
  surveyId: string;
  answers: Record<string, AnswerValue>;
  patientInfo: PatientInfo;
  submittedAt: string;
  department: string;
  overallScore: number;
}

export interface PatientInfo {
  name: string;
  phone: string;
  ageGroup: string;
  gender: string;
  visitType: string;
  department: string;
}

export interface DashboardStats {
  totalResponses: number;
  averageScore: number;
  previousAverageScore?: number;
  npsScore: number;
  previousNpsScore?: number;
  responseRate: number;
  previousResponseRate?: number;
  departmentScores: { name: string; score: number; count: number }[];
  trendData: { date: string; score: number; count: number }[];
  categoryScores: { category: string; score: number }[];
  satisfactionDistribution: { level: string; count: number; color: string }[];
  hourlyStats: { hour: string; score: number; count: number }[];
  dayStats: { day: string; score: number; count: number }[];
}

export type TicketStatus = 'open' | 'in_progress' | 'resolved';
export type TicketPriority = 'high' | 'medium' | 'low';

export interface Ticket {
  id: string;
  responseId: string;
  department: string;
  patientName: string;
  patientPhone: string;
  priority: TicketPriority;
  status: TicketStatus;
  description: string;
  createdAt: string;
  resolvedAt?: string;
  resolutionNotes?: string;
  assignedTo?: string;
}
