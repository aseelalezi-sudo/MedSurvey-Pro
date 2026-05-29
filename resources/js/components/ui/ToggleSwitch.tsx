import React from "react";

interface ToggleSwitchProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  label?: string;
  disabled?: boolean;
  className?: string;
}

export function ToggleSwitch({ checked, onChange, label, disabled = false, className = "" }: ToggleSwitchProps) {
  return (
    <label className={`inline-flex items-center gap-2 cursor-pointer ${disabled ? "opacity-50 cursor-not-allowed" : ""} ${className}`}>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => !disabled && onChange(!checked)}
        className={`w-14 h-7 rounded-full transition-all relative cursor-pointer disabled:cursor-not-allowed ${
          checked
            ? "bg-teal-500 dark:bg-teal-600"
            : "bg-gray-300 dark:bg-slate-600"
        }`}
      >
        <span
          className={`absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow-md transition-transform duration-200 ${
            checked ? "translate-x-7" : "translate-x-0"
          }`}
        />
      </button>
      {label && <span className="text-sm text-gray-700 dark:text-gray-300">{label}</span>}
    </label>
  );
}
