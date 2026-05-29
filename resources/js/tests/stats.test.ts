import { describe, it, expect } from 'vitest';
import { analyticsService } from '../services/analyticsService';
import { SurveyResponse, PatientInfo } from '../types';

describe('Statistics Calculations', () => {
  it('should calculate the correct average score and NPS', () => {
    const patientInfo: PatientInfo = { name: '', phone: '', ageGroup: '', gender: '', visitType: '', department: 'الطوارئ' };
    const responses: SurveyResponse[] = [
      { id: '1', surveyId: 's1', answers: { q13: 10 }, patientInfo, submittedAt: new Date().toISOString(), department: 'الطوارئ', overallScore: 90 },
      { id: '2', surveyId: 's1', answers: { q13: 8 }, patientInfo, submittedAt: new Date().toISOString(), department: 'الطوارئ', overallScore: 70 },
      { id: '3', surveyId: 's1', answers: { q13: 2 }, patientInfo, submittedAt: new Date().toISOString(), department: 'الجراحة', overallScore: 40 },
    ];

    const stats = analyticsService.calculateDashboardStats(responses);
    
    // Average score: (90 + 70 + 40) / 3 = 67
    expect(stats.averageScore).toBe(67);
    
    // NPS: 1 promoter (10), 1 passive (8), 1 detractor (2)
    // NPS = ((1 - 1) / 3) * 100 = 0
    expect(stats.npsScore).toBe(0);
    
    expect(stats.totalResponses).toBe(3);
    
    // Dept scores
    const emergencyDept = stats.departmentScores.find(d => d.name === 'الطوارئ');
    expect(emergencyDept?.score).toBe(80); // (90 + 70) / 2
  });
});
