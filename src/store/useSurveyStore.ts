import { create } from 'zustand';
import { SurveyTemplate, PatientInfo, AnswerValue } from '../types';
import { surveysAPI, responsesAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('SurveyStore');

interface SurveyState {
  // Survey list
  surveys: SurveyTemplate[];
  loadingSurveys: boolean;

  // Active survey session
  selectedSurvey: SurveyTemplate | null;
  currentSection: number;
  answers: Record<string, AnswerValue>;
  patientInfo: PatientInfo;
  selectedTip: string;

  // Actions — Data loading
  loadSurveys: () => Promise<void>;

  // Actions — Survey session
  selectSurvey: (surveyId: string) => void;
  setCurrentSection: (section: number) => void;
  nextSection: () => void;
  prevSection: () => boolean; // returns false if at first section
  setAnswer: (questionId: string, value: AnswerValue) => void;
  updatePatientInfo: (field: keyof PatientInfo, value: string) => void;
  resetSurveySession: () => void;
  submitSurvey: () => Promise<boolean>;

  // Actions — Survey CRUD (admin)
  saveSurvey: (survey: SurveyTemplate) => Promise<void>;
  deleteSurvey: (id: string) => Promise<void>;
}

const initialPatientInfo: PatientInfo = {
  name: '',
  phone: '',
  ageGroup: '',
  gender: '',
  visitType: '',
  department: '',
};

export const useSurveyStore = create<SurveyState>((set, get) => ({
  // Initial state
  surveys: [],
  loadingSurveys: false,
  selectedSurvey: null,
  currentSection: 0,
  answers: {},
  patientInfo: { ...initialPatientInfo },
  selectedTip: '',

  // Load surveys from API
  loadSurveys: async () => {
    set({ loadingSurveys: true });
    try {
      const data = await surveysAPI.getAll();
      set({ surveys: data as SurveyTemplate[], loadingSurveys: false });
    } catch (error) {
      logger.error('Failed to load surveys:', error);
      set({ loadingSurveys: false });
    }
  },

  // Select a survey by ID and reset session state
  selectSurvey: (surveyId: string) => {
    const survey = get().surveys.find(s => s.id === surveyId) || null;
    set({
      selectedSurvey: survey,
      currentSection: 0,
      answers: {},
      patientInfo: { ...initialPatientInfo },
      selectedTip: '',
    });
  },

  setCurrentSection: (section: number) => {
    set({ currentSection: section });
  },

  nextSection: () => {
    const { currentSection, selectedSurvey } = get();
    if (selectedSurvey && currentSection < selectedSurvey.sections.length - 1) {
      set({ currentSection: currentSection + 1 });
    }
  },

  prevSection: () => {
    const { currentSection } = get();
    if (currentSection > 0) {
      set({ currentSection: currentSection - 1 });
      return true;
    }
    return false;
  },

  setAnswer: (questionId: string, value: AnswerValue) => {
    set(state => ({
      answers: { ...state.answers, [questionId]: value },
    }));
  },

  updatePatientInfo: (field: keyof PatientInfo, value: string) => {
    set(state => ({
      patientInfo: { ...state.patientInfo, [field]: value },
    }));
  },

  resetSurveySession: () => {
    set({
      selectedSurvey: null,
      currentSection: 0,
      answers: {},
      patientInfo: { ...initialPatientInfo },
      selectedTip: '',
    });
  },

  // Submit survey response
  submitSurvey: async () => {
    const { selectedSurvey, answers, patientInfo } = get();
    if (!selectedSurvey) return false;

    try {
      // Calculate overall score from numeric answers
      const numericAnswers = Object.entries(answers)
        .filter(([key, val]) => typeof val === 'number' && !key.endsWith('_reason'));
      const totalScore = numericAnswers.reduce((sum, [, val]) => sum + (val as number), 0);
      const maxScore = numericAnswers.length * 5;
      const overallScore = maxScore > 0 ? Math.round((totalScore / maxScore) * 100) : 0;

      await responsesAPI.create({
        surveyId: selectedSurvey.id,
        answers,
        patientInfo,
        department: patientInfo.department,
        overallScore,
        submittedAt: new Date().toISOString(),
      });

      // Pick a random tip
      const tips = selectedSurvey.tips || [];
      const tip = tips.length > 0 ? tips[Math.floor(Math.random() * tips.length)] : '';
      set({ selectedTip: tip });

      return true;
    } catch (error) {
      logger.error('Failed to submit survey:', error);
      return false;
    }
  },

  // Admin: Save (create or update) a survey
  saveSurvey: async (survey: SurveyTemplate) => {
    try {
      const existing = get().surveys.find(s => s.id === survey.id);
      if (existing && !survey.id.startsWith('survey-')) {
        // Update existing survey
        await surveysAPI.update(survey.id, survey);
      } else {
        // Create new survey
        await surveysAPI.create(survey);
      }
      // Reload surveys to get fresh data from server
      await get().loadSurveys();
    } catch (error) {
      logger.error('Failed to save survey:', error);
    }
  },

  // Admin: Delete a survey
  deleteSurvey: async (id: string) => {
    try {
      await surveysAPI.delete(id);
      await get().loadSurveys();
    } catch (error) {
      logger.error('Failed to delete survey:', error);
    }
  },
}));
