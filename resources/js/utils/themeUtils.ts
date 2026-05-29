type Theme = "light" | "dark";

export function applyTheme(theme: Theme): void {
  if (typeof window === "undefined") return;

  localStorage.setItem("theme", theme);
  document.documentElement.classList.toggle("dark", theme === "dark");
}

export function getInitialTheme(): Theme {
  if (typeof window === "undefined") return "light";

  const stored = localStorage.getItem("theme") as Theme | null;
  if (stored === "dark" || stored === "light") return stored;

  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}

export function toggleTheme(currentTheme: Theme): Theme {
  const next = currentTheme === "dark" ? "light" : "dark";
  applyTheme(next);
  return next;
}
