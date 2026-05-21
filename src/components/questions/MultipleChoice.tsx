import { Check } from 'lucide-react';
import { QuestionOption } from '../../types';

interface MultipleChoiceProps {
  options: QuestionOption[];
  value: string;
  onChange: (value: string) => void;
}

export default function MultipleChoice({ options, value, onChange }: MultipleChoiceProps) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 py-4">
      {options.map((option) => (
        <button
          key={option.id}
          onClick={() => onChange(option.value)}
          className={`flex min-w-0 items-center gap-3 p-4 rounded-xl border-2 text-right transition-all duration-200 ${
            value === option.value
              ? 'bg-teal-50 border-teal-500 text-teal-700 shadow-md'
              : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 hover:border-gray-300'
          }`}
        >
          <div className={`w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 ${
            value === option.value
              ? 'bg-teal-500 border-teal-500'
              : 'border-gray-300'
          }`}>
            {value === option.value && <Check className="w-4 h-4 text-white" />}
          </div>
          <span className="min-w-0 break-words font-medium text-sm sm:text-base">{option.label}</span>
        </button>
      ))}
    </div>
  );
}
