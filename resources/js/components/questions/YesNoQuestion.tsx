import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface YesNoQuestionProps {
  value: string;
  onChange: (value: string) => void;
}

export default function YesNoQuestion({ value, onChange }: YesNoQuestionProps) {
  const { t } = useTranslation();

  return (
    <div className="flex items-center justify-center gap-4 py-4">
      <button
        onClick={() => onChange('yes')}
        className={`flex items-center gap-3 px-8 py-4 rounded-2xl border-2 font-bold text-lg transition-all duration-200 transform hover:scale-105 ${
          value === 'yes'
            ? 'bg-green-100 border-green-500 text-green-700 shadow-lg shadow-green-100'
            : 'bg-white border-gray-200 text-gray-600 hover:bg-green-50 hover:border-green-300'
        }`}
      >
        <Check className={`w-6 h-6 ${value === 'yes' ? 'text-green-600' : 'text-gray-400'}`} />
        {t('responses_yes')}
      </button>
      <button
        onClick={() => onChange('no')}
        className={`flex items-center gap-3 px-8 py-4 rounded-2xl border-2 font-bold text-lg transition-all duration-200 transform hover:scale-105 ${
          value === 'no'
            ? 'bg-red-100 border-red-500 text-red-700 shadow-lg shadow-red-100'
            : 'bg-white border-gray-200 text-gray-600 hover:bg-red-50 hover:border-red-300'
        }`}
      >
        <X className={`w-6 h-6 ${value === 'no' ? 'text-red-600' : 'text-gray-400'}`} />
        {t('responses_no')}
      </button>
    </div>
  );
}
