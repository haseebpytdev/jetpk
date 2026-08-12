"use client";

import { THEME_VALUES, type ThemePreference } from "@/lib/theme/constants";
import { cn } from "@/lib/cn";
import { useTheme } from "./ThemeProvider";

const LABELS: Record<ThemePreference, string> = {
  system: "System theme",
  light: "Light theme",
  dark: "Dark theme",
};

const ICONS: Record<ThemePreference, string> = {
  system: "Auto",
  light: "Light",
  dark: "Dark",
};

type ThemeSwitchProps = {
  className?: string;
  compact?: boolean;
};

export function ThemeSwitch({ className, compact = false }: ThemeSwitchProps) {
  const { preference, setPreference } = useTheme();

  const cyclePreference = () => {
    const index = THEME_VALUES.indexOf(preference);
    const next = THEME_VALUES[(index + 1) % THEME_VALUES.length];
    setPreference(next);
  };

  return (
    <button
      type="button"
      onClick={cyclePreference}
      className={cn(
        "inline-flex min-h-9 items-center gap-1.5 rounded-jp-md border border-jp-border bg-jp-surface px-2 py-1.5",
        "text-jp-sm font-medium text-jp-text transition-colors duration-ui",
        "hover:bg-jp-surface-muted focus-visible:outline-none focus-visible:shadow-jp-focus",
        "motion-reduce:transition-none",
        compact && "w-full justify-center gap-2 px-3",
        className,
      )}
      aria-label={`Theme: ${LABELS[preference]}. Activate to switch theme.`}
      data-testid="theme-switch"
      data-theme-preference={preference}
    >
      <ThemeIcon preference={preference} />
      <span aria-hidden="true">{compact ? LABELS[preference] : ICONS[preference]}</span>
      <span className="sr-only">{LABELS[preference]}</span>
    </button>
  );
}

function ThemeIcon({ preference }: { preference: ThemePreference }) {
  if (preference === "light") {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0" aria-hidden="true">
        <circle cx="12" cy="12" r="4" fill="currentColor" />
        <path
          d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
        />
      </svg>
    );
  }

  if (preference === "dark") {
    return (
      <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0" aria-hidden="true">
        <path
          d="M21 14.5A8.5 8.5 0 1 1 9.5 3a6.5 6.5 0 0 0 11.5 11.5Z"
          fill="currentColor"
        />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0" aria-hidden="true">
      <rect x="3" y="5" width="18" height="12" rx="2" stroke="currentColor" strokeWidth="2" fill="none" />
      <path d="M8 21h8" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}
