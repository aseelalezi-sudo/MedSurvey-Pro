export interface HospitalInfo {
  name: string;
  shortName: string;
  logo: string;
  address: string;
  phone: string;
  email: string;
  website: string;
  description: string;
  workingHours: string;
  operatingTitle: string;
  welcomeMessage: string;
}

export interface Department {
  id: string;
  name: string;
  isActive: boolean;
  color: string;
}

export interface AgeGroup {
  id: string;
  label: string;
  isActive: boolean;
}

export interface VisitType {
  id: string;
  label: string;
  isActive: boolean;
}

export interface SystemSettings {
  hospital: HospitalInfo;
  departments: Department[];
  ageGroups: AgeGroup[];
  visitTypes: VisitType[];
  surveySettings: {
    allowAnonymous: boolean;
    requireAllQuestions: boolean;
    requireName: boolean;
    requirePhone: boolean;
    showProgressBar: boolean;
    enableThankYouPage: boolean;
    thankYouMessage: string;
  };
  appearance: {
    primaryColor: string;
    secondaryColor: string;
    fontFamily: string;
    showLanguageToggle?: boolean;
  };
  activatedPredictivePlans: string[];
}
