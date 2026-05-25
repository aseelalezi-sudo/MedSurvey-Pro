import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { useSurveyStore } from '../store/useSurveyStore';

export function useQuestionTitle() {
  const { surveys } = useSurveyStore();
  const { t } = useTranslation();

  const getQuestionTitle = useCallback((
    surveyId: string,
    questionId: string,
    answersObj?: Record<string, string | number | boolean | string[] | null>
  ): string => {
    if (questionId.endsWith('_reason')) {
      return t('reason_for_low_rating_label', 'سبب تدني التقييم');
    }

    let survey = surveys.find(s => s.id === surveyId);
    if (!survey && surveys.length > 0) {
      survey = surveys[0]; // fallback
    }
    if (!survey) return questionId;

    // Flatten all questions to support index-based lookups for seed data (q1, q2...)
    const allQuestions: { id: string; title: string }[] = [];
    survey.sections.forEach(sec => {
      allQuestions.push(...sec.questions);
    });

    // 1. Direct match by ID (for actual real responses)
    const directQuestion = allQuestions.find(q => q.id === questionId);
    if (directQuestion) return directQuestion.title;

    // 2. If it's a seed question (e.g. "q1", "q2", "q13"...)
    if (/^q\d+$/.test(questionId)) {
      const index = parseInt(questionId.substring(1)) - 1;
      if (index >= 0 && index < allQuestions.length) {
        return allQuestions[index].title;
      }
    }

    // 3. Fallback: Match by sequential index of the answer key within answersObj (for regenerated IDs from updated surveys)
    if (answersObj) {
      const keys = Object.keys(answersObj).filter(k => !k.endsWith('_reason')); // ignore follow-up reason keys
      const keyIndex = keys.indexOf(questionId);
      if (keyIndex >= 0 && keyIndex < allQuestions.length) {
        return allQuestions[keyIndex].title;
      }
    }

    return questionId;
  }, [surveys, t]);

  return { getQuestionTitle };
}
