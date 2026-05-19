import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { SurveyTemplate, PatientInfo, AnswerValue } from '../types';
import { surveysAPI, responsesAPI } from '../api/client';
import { createLogger } from '../utils/logger';

const logger = createLogger('SurveyStore');

const SESSION_DURATION_MS = 3 * 60 * 1000;

export interface SessionTimer {
  remainingMs: number;
  paused: boolean;
  interactionTick: number;
}

interface SurveyState {
  surveys: SurveyTemplate[];
  loadingSurveys: boolean;

  selectedSurvey: SurveyTemplate | null;
  currentSection: number;
  answers: Record<string, AnswerValue>;
  patientInfo: PatientInfo;
  selectedTip: string;
  sessionTimer: SessionTimer | null;

  loadSurveys: () => Promise<void>;

  selectSurvey: (surveyId: string) => void;
  setCurrentSection: (section: number) => void;
  nextSection: () => void;
  prevSection: () => boolean;
  setAnswer: (questionId: string, value: AnswerValue) => void;
  updatePatientInfo: (field: keyof PatientInfo, value: string) => void;
  startSurveySessionTimer: () => void;
  clearSurveySessionTimer: () => void;
  resetSurveySession: () => void;
  submitSurvey: () => Promise<boolean>;

  reportInteraction: () => void;
  pauseSessionTimer: () => void;
  resumeSessionTimer: () => void;
  decrementSessionTimer: () => void;

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

export const useSurveyStore = create<SurveyState>()(
  persist(
    (set, get) => ({
      surveys: [],
      loadingSurveys: false,
      selectedSurvey: null,
      currentSection: 0,
      answers: {},
      patientInfo: { ...initialPatientInfo },
      selectedTip: '',
      sessionTimer: null,

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
    get().reportInteraction();
  },

  nextSection: () => {
    const { currentSection, selectedSurvey } = get();
    if (selectedSurvey && currentSection < selectedSurvey.sections.length - 1) {
      set({ currentSection: currentSection + 1 });
    }
    get().reportInteraction();
  },

  prevSection: () => {
    const { currentSection } = get();
    if (currentSection > 0) {
      set({ currentSection: currentSection - 1 });
    }
    get().reportInteraction();
    return currentSection > 0;
  },

  setAnswer: (questionId: string, value: AnswerValue) => {
    set(state => ({
      answers: { ...state.answers, [questionId]: value },
    }));
    get().reportInteraction();
  },

  updatePatientInfo: (field: keyof PatientInfo, value: string) => {
    set(state => ({
      patientInfo: { ...state.patientInfo, [field]: value },
    }));
    get().reportInteraction();
  },

  reportInteraction: () => {
    const { sessionTimer } = get();
    if (!sessionTimer || sessionTimer.remainingMs <= 0) return;
    set({
      sessionTimer: {
        ...sessionTimer,
        paused: true,
        interactionTick: sessionTimer.interactionTick + 1,
      },
    });
  },

  startSurveySessionTimer: () => {
    set({
      sessionTimer: {
        remainingMs: SESSION_DURATION_MS,
        paused: false,
        interactionTick: 0,
      },
    });
  },

  clearSurveySessionTimer: () => {
    set({ sessionTimer: null });
  },

  resetSurveySession: () => {
    set({
      selectedSurvey: null,
      currentSection: 0,
      answers: {},
      patientInfo: { ...initialPatientInfo },
      selectedTip: '',
      sessionTimer: null,
    });
  },

  pauseSessionTimer: () => {
    const { sessionTimer } = get();
    if (!sessionTimer || sessionTimer.paused) return;
    set({ sessionTimer: { ...sessionTimer, paused: true } });
  },

  resumeSessionTimer: () => {
    const { sessionTimer } = get();
    if (!sessionTimer || !sessionTimer.paused || sessionTimer.remainingMs <= 0) return;
    set({ sessionTimer: { ...sessionTimer, paused: false } });
  },

  decrementSessionTimer: () => {
    const { sessionTimer } = get();
    if (!sessionTimer || sessionTimer.paused || sessionTimer.remainingMs <= 0) return;
    set({ sessionTimer: { ...sessionTimer, remainingMs: sessionTimer.remainingMs - 1000 } });
  },

  submitSurvey: async () => {
    const { selectedSurvey, answers, patientInfo } = get();
    if (!selectedSurvey) return false;

    try {
      let totalScore = 0;
      let maxScore = 0;

      selectedSurvey.sections.forEach(section => {
        section.questions.forEach(q => {
          const val = answers[q.id];
          if (typeof val === 'number') {
            if (q.type === 'nps') {
              totalScore += val;
              maxScore += 10;
            } else if (q.type === 'stars' || q.type === 'emoji' || q.type === 'rating') {
              totalScore += val;
              maxScore += 5;
            }
          }
        });
      });

      const calculatedScore = maxScore > 0 ? Math.round((totalScore / maxScore) * 100) : 0;
      const overallScore = Math.min(100, Math.max(0, calculatedScore));

      await responsesAPI.create({
        surveyId: selectedSurvey.id,
        answers,
        patientInfo,
        department: patientInfo.department,
        overallScore,
        submittedAt: new Date().toISOString(),
      });

      const tips = selectedSurvey.tips || [];
      const tip = tips.length > 0 ? tips[Math.floor(Math.random() * tips.length)] : '';
      set({ selectedTip: tip });

      return true;
    } catch (error) {
      logger.error('Failed to submit survey:', error);
      return false;
    }
  },

  saveSurvey: async (survey: SurveyTemplate) => {
    try {
      const existing = get().surveys.find(s => s.id === survey.id);
      if (existing && !survey.id.startsWith('survey-')) {
        await surveysAPI.update(survey.id, survey);
      } else {
        await surveysAPI.create(survey);
      }
      await get().loadSurveys();
    } catch (error) {
      logger.error('Failed to save survey:', error);
    }
  },

  deleteSurvey: async (id: string) => {
    try {
      await surveysAPI.delete(id);
      await get().loadSurveys();
    } catch (error) {
      logger.error('Failed to delete survey:', error);
    }
  },
}), {
  name: 'survey-store',
  partialize: (state) => ({
    selectedSurvey: state.selectedSurvey,
    currentSection: state.currentSection,
    answers: state.answers,
    patientInfo: state.patientInfo,
    selectedTip: state.selectedTip,
    sessionTimer: state.sessionTimer,
  }),
}));
