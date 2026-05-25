import { Star } from 'lucide-react';

interface StarRatingProps {
  value: number;
  onChange: (value: number) => void;
  maxStars?: number;
}

export default function StarRating({ value, onChange, maxStars = 5 }: StarRatingProps) {
  return (
    <div className="flex items-center justify-center gap-1.5 sm:gap-2 py-4 flex-wrap">
      {Array.from({ length: maxStars }, (_, i) => i + 1).map((star) => (
        <button
          key={star}
          onClick={() => onChange(star)}
          className={`transition-all duration-200 transform hover:scale-125 ${
            star <= value 
              ? 'text-amber-400 drop-shadow-lg' 
              : 'text-gray-300 hover:text-amber-200'
          }`}
        >
          <Star 
            className={`w-9 h-9 min-[380px]:w-10 min-[380px]:h-10 sm:w-12 sm:h-12 ${star <= value ? 'fill-amber-400' : ''}`} 
          />
        </button>
      ))}
    </div>
  );
}
