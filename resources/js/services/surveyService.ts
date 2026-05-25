import { SurveyTemplate, AnswerValue } from '../types';

export const surveyService = {
  /**
   * Calculates the overall satisfaction score of a survey based on its answers.
   * NPS questions are weighted out of 10, other numeric ratings (stars, emoji, ratings) are weighted out of 5.
   */
  calculateOverallScore(surveyTemplate: SurveyTemplate, answers: Record<string, AnswerValue>): number {
    let totalScore = 0;
    let maxScore = 0;

    surveyTemplate.sections.forEach(section => {
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
    return Math.min(100, Math.max(0, calculatedScore));
  }
};
