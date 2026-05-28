

export function CustomProjectIcon({ className }: { className?: string }) {
  return (
    <img 
      src="/system-logo.png" 
      alt="Project Logo" 
      className={`object-contain ${className || ''}`}
      onError={(e) => {
        // Fallback styling if image is missing
        (e.target as HTMLImageElement).style.display = 'none';
      }}
    />
  );
}
