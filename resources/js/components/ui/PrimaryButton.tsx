import React from "react";

interface PrimaryButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "danger" | "ghost";
  size?: "sm" | "md" | "lg";
  loading?: boolean;
}

export function PrimaryButton({
  children,
  className = "",
  variant = "primary",
  size = "md",
  loading = false,
  disabled,
  ...props
}: PrimaryButtonProps) {
  const baseClasses = "font-bold rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 inline-flex items-center justify-center gap-2";

  const variantClasses: Record<string, string> = {
    primary: "bg-linear-to-r from-teal-600 to-emerald-600 text-white shadow-teal-200 dark:shadow-teal-950/20",
    secondary: "bg-gray-200 dark:bg-slate-700 text-gray-800 dark:text-gray-200 shadow-gray-200 dark:shadow-slate-950/20 hover:bg-gray-300 dark:hover:bg-slate-600",
    danger: "bg-linear-to-r from-red-600 to-rose-600 text-white shadow-red-200 dark:shadow-red-950/20",
    ghost: "bg-transparent text-gray-700 dark:text-gray-300 shadow-none hover:bg-gray-100 dark:hover:bg-slate-800",
  };

  const sizeClasses: Record<string, string> = {
    sm: "px-4 py-2 text-sm",
    md: "px-6 py-3 text-base",
    lg: "px-8 py-4 text-lg",
  };

  return (
    <button
      className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
      disabled={disabled || loading}
      {...props}
    >
      {loading && (
        <svg className="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      )}
      {children}
    </button>
  );
}
