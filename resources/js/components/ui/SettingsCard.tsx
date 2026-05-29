import React from "react";

interface SettingsCardProps {
  children: React.ReactNode;
  title?: string;
  description?: string;
  className?: string;
  animate?: boolean;
}

export function SettingsCard({ children, title, description, className = "", animate = false }: SettingsCardProps) {
  return (
    <div
      className={`bg-white dark:bg-slate-900 rounded-2xl p-6 border border-gray-100 dark:border-slate-800 shadow-sm text-start${animate ? " animate-fade-in" : ""} ${className}`}
    >
      {title && (
        <div className="mb-4">
          <h3 className="text-lg font-bold text-gray-900 dark:text-white">{title}</h3>
          {description && (
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{description}</p>
          )}
        </div>
      )}
      {children}
    </div>
  );
}
