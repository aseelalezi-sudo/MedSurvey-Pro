export interface PredictiveAlert {
  id: string;
  department: string;
  previousAvg: number;
  currentAvg: number;
  predictedScore: number;
  drop: number;
  dropPercentage: number;
  keyDriver: string;
  sampleCount: number;
  lastResponseDate: string;
}

export interface PredictiveStats {
  totalDepts: number;
  activeWarnings: number;
  healthIndex: number;
  totalResponsesAnalyzed: number;
}
