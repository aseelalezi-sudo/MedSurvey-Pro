interface NPSRatingProps {
  value: number;
  onChange: (value: number) => void;
}

export default function NPSRating({ value, onChange }: NPSRatingProps) {
  return (
    <div className="py-4 min-w-0">
      <div className="grid grid-cols-6 min-[420px]:grid-cols-11 gap-1.5 sm:gap-2">
        {Array.from({ length: 11 }, (_, i) => i).map((num) => {
          const isSelected = value === num;
          const getColor = () => {
            if (num <= 6) return isSelected ? 'bg-red-500 text-white border-red-500' : 'hover:bg-red-50 hover:border-red-300';
            if (num <= 8) return isSelected ? 'bg-amber-500 text-white border-amber-500' : 'hover:bg-amber-50 hover:border-amber-300';
            return isSelected ? 'bg-green-500 text-white border-green-500' : 'hover:bg-green-50 hover:border-green-300';
          };

          return (
            <button
              key={num}
              onClick={() => onChange(num)}
              className={`w-full aspect-square min-h-9 max-h-12 rounded-xl border-2 font-bold text-sm sm:text-base transition-all duration-200 ${
                isSelected ? getColor() : `bg-white border-gray-200 text-gray-700 ${getColor()}`
              } ${isSelected ? 'scale-110 shadow-lg' : 'hover:scale-105'}`}
            >
              {num}
            </button>
          );
        })}
      </div>
      <div className="flex justify-between gap-3 mt-4 px-2 text-xs sm:text-sm text-gray-500">
        <span className="text-red-500">لن أوصي أبداً</span>
        <span className="text-green-500">سأوصي بالتأكيد</span>
      </div>
    </div>
  );
}
