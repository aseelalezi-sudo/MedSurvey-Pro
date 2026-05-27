import { MessageSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface TextQuestionProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
}

export default function TextQuestion({ value, onChange, placeholder }: TextQuestionProps) {
  const { t } = useTranslation();

  return (
    <div className="py-4">
      <div className="relative">
        <MessageSquare className="absolute top-4 right-4 w-5 h-5 text-gray-400" />
        <textarea
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder || t('text_answer_placeholder')}
          rows={4}
          className="w-full pr-12 pl-4 py-4 rounded-xl border-2 border-gray-200 focus:border-teal-500 focus:ring-4 focus:ring-teal-100 outline-none resize-none transition-all text-gray-700 placeholder-gray-400"
        />
      </div>
      <div className="text-left mt-2">
        <span className="text-xs text-gray-400">{t('text_answer_counter', { count: value.length })}</span>
      </div>
    </div>
  );
}
