interface EmojiRatingProps {
  value: number;
  onChange: (value: number) => void;
}

const emojis = [
  { value: 1, emoji: '😡', label: 'سيء جداً', color: 'bg-red-100 border-red-300 text-red-500' },
  { value: 2, emoji: '😕', label: 'سيء', color: 'bg-orange-100 border-orange-300 text-orange-500' },
  { value: 3, emoji: '😐', label: 'متوسط', color: 'bg-yellow-100 border-yellow-300 text-yellow-600' },
  { value: 4, emoji: '😊', label: 'جيد', color: 'bg-lime-100 border-lime-300 text-lime-600' },
  { value: 5, emoji: '😍', label: 'ممتاز', color: 'bg-green-100 border-green-300 text-green-500' },
];

export default function EmojiRating({ value, onChange }: EmojiRatingProps) {
  return (
    <div className="grid grid-cols-2 min-[430px]:grid-cols-5 gap-2 sm:gap-3 py-4">
      {emojis.map((item) => (
        <button
          key={item.value}
          onClick={() => onChange(item.value)}
          className={`flex min-w-0 flex-col items-center gap-2 p-3 sm:p-4 rounded-2xl border-2 transition-all duration-200 transform hover:scale-105 ${
            value === item.value
              ? `${item.color} border-2 shadow-lg scale-110`
              : 'bg-gray-50 border-gray-200 hover:bg-gray-100'
          }`}
        >
          <span className="text-3xl sm:text-4xl leading-none">{item.emoji}</span>
          <span className={`text-xs font-medium text-center break-words ${value === item.value ? '' : 'text-gray-500'}`}>
            {item.label}
          </span>
        </button>
      ))}
    </div>
  );
}
