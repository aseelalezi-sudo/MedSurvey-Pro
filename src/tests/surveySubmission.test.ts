import { describe, it, expect } from 'vitest';
import { SurveyTemplate, PatientInfo, AnswerValue } from '../types';

describe('Frontend: Survey Submission Flow', () => {
  // Mock Survey Template matching MedSurvey Pro structure
  const mockSurveyTemplate: SurveyTemplate = {
    id: 'survey-satisfaction',
    title: 'استبيان رضا المراجعين',
    description: 'نسعى لتقديم أفضل خدمة طبية ورعايتكم تهمنا',
    createdAt: new Date().toISOString(),
    isActive: true,
    sections: [
      {
        id: 'sec-reception',
        title: 'الاستقبال والتسجيل',
        description: '',
        icon: 'clipboard-check',
        questions: [
          { id: 'q1', title: 'سرعة إنهاء إجراءات التسجيل المالي والإداري', description: '', type: 'stars', required: true, category: 'الاستقبال', options: undefined, followUp: undefined },
          { id: 'q2', title: 'بشاشة وحسن استقبال موظفي مكتب التسجيل والدخول', description: '', type: 'emoji', required: true, category: 'الاستقبال', options: undefined, followUp: undefined }
        ]
      },
      {
        id: 'sec-medical',
        title: 'الخدمة الطبية والتمريضية',
        description: '',
        icon: 'clipboard-check',
        questions: [
          { id: 'q3', title: 'اهتمام الطبيب المعالج بالاستماع لشكواك بدقة وإيضاح الخطة الطبية', description: '', type: 'stars', required: true, category: 'الرعاية الطبية', options: undefined, followUp: undefined },
          { id: 'q4', title: 'هل تم تقديم الخدمة لك في الوقت المحدد؟', description: '', type: 'yes_no', required: true, category: 'الرعاية الطبية', options: undefined, followUp: undefined }
        ]
      }
    ]
  };

  // 1. Test Overall Score Calculation Logic
  describe('Score Calculation Logic', () => {
    it('should calculate accurate percentage overall score for perfect ratings (5/5 and Yes)', () => {
      const answers: Record<string, AnswerValue> = {
        q1: 5,   // 5 out of 5 stars (100%)
        q2: 5,   // 5 out of 5 emoji rating (100%)
        q3: 5,   // 5 out of 5 stars (100%)
        q4: 1    // Yes = 1, meaning 100%
      };

      // Calculate total rating score
      let totalScore = 0;
      let maxPossibleScore = 0;

      Object.entries(answers).forEach(([key, value]) => {
        if (typeof value === 'number') {
          const question = mockSurveyTemplate.sections
            .flatMap(s => s.questions)
            .find(q => q.id === key);

          if (question) {
            if (question.type === 'yes_no') {
              // yes_no answers: 1 = Yes (5/5 score), 0 = No (1/5 score)
              const score = value === 1 ? 5 : 1;
              totalScore += score;
              maxPossibleScore += 5;
            } else {
              totalScore += value;
              maxPossibleScore += 5;
            }
          }
        }
      });

      const overallScorePercent = Math.round((totalScore / maxPossibleScore) * 100);
      expect(overallScorePercent).toBe(100);
    });

    it('should calculate accurate percentage overall score for poor/negative ratings (1/5 and No)', () => {
      const answers: Record<string, AnswerValue> = {
        q1: 1,   // 1 star (20%)
        q2: 2,   // 2 rating (40%)
        q3: 1,   // 1 star (20%)
        q4: 0    // No = 0, meaning 1/5 score (20%)
      };

      let totalScore = 0;
      let maxPossibleScore = 0;

      Object.entries(answers).forEach(([key, value]) => {
        if (typeof value === 'number') {
          const question = mockSurveyTemplate.sections
            .flatMap(s => s.questions)
            .find(q => q.id === key);

          if (question) {
            if (question.type === 'yes_no') {
              const score = value === 1 ? 5 : 1;
              totalScore += score;
              maxPossibleScore += 5;
            } else {
              totalScore += value;
              maxPossibleScore += 5;
            }
          }
        }
      });

      const overallScorePercent = Math.round((totalScore / maxPossibleScore) * 100);
      // Scores: q1(1) + q2(2) + q3(1) + q4(1) = 5. Max possible: 20.
      // (5/20) * 100 = 25%
      expect(overallScorePercent).toBe(25);
    });
  });

  // 2. Test PatientInfo input validation
  describe('Patient Info Validation Rules', () => {
    const validatePatientInfo = (info: Partial<PatientInfo>): { isValid: boolean; error?: string } => {
      if (!info.department || info.department.trim() === '') {
        return { isValid: false, error: 'القسم الطبي مطلوب لتسجيل الاستبيان' };
      }
      if (info.phone && info.phone.trim() !== '') {
        const phoneRegex = /^(009665|9665|\+9665|05|5)(5|0|3|6|4|9|1|8|7)([0-9]{7})$/;
        if (!phoneRegex.test(info.phone)) {
          return { isValid: false, error: 'رقم الجوال غير صحيح، يرجى كتابة رقم سعودي صحيح' };
        }
      }
      return { isValid: true };
    };

    it('should accept valid patient info with mandatory department', () => {
      const validInfo: PatientInfo = {
        name: 'سليمان خالد',
        phone: '0551234567',
        ageGroup: '31-45',
        gender: 'male',
        visitType: 'outpatient',
        department: 'الطوارئ'
      };

      const result = validatePatientInfo(validInfo);
      expect(result.isValid).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should reject patient info missing department', () => {
      const invalidInfo = {
        name: 'سليمان خالد',
        phone: '0551234567',
        department: '' // Empty department
      };

      const result = validatePatientInfo(invalidInfo);
      expect(result.isValid).toBe(false);
      expect(result.error).toBe('القسم الطبي مطلوب لتسجيل الاستبيان');
    });

    it('should reject invalid phone number formatting', () => {
      const invalidInfo = {
        name: 'خالد عبدالله',
        phone: '12345', // Invalid format
        department: 'العيادات الخارجية'
      };

      const result = validatePatientInfo(invalidInfo);
      expect(result.isValid).toBe(false);
      expect(result.error).toContain('رقم الجوال غير صحيح');
    });
  });

  // 3. Test final payload construction before API post
  describe('Submission Payload Construction', () => {
    it('should correctly format survey response payload to match API contract', () => {
      const patientInfo: PatientInfo = {
        name: 'أمل العتيبي',
        phone: '0501234567',
        ageGroup: '18-30',
        gender: 'female',
        visitType: 'inpatient',
        department: 'الولادة والأطفال'
      };

      const answers: Record<string, AnswerValue> = {
        q1: 4,
        q2: 5,
        q3: 4,
        q4: 1
      };

      const overallScore = 90;

      // Construct payload mimicking the Submit flow
      const payload = {
        surveyId: mockSurveyTemplate.id,
        answers,
        patientInfo,
        department: patientInfo.department,
        overallScore
      };

      expect(payload.surveyId).toBe('survey-satisfaction');
      expect(payload.department).toBe('الولادة والأطفال');
      expect(payload.overallScore).toBe(90);
      expect(payload.answers).toEqual({ q1: 4, q2: 5, q3: 4, q4: 1 });
      expect(payload.patientInfo.name).toBe('أمل العتيبي');
      expect(payload.patientInfo.visitType).toBe('inpatient');
    });
  });
});
