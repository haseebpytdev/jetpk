"use client";

import { THEME_VALUES, type ThemePreference } from "@/lib/theme/constants";
import { cn } from "@/lib/cn";
import { useTheme } from "./ThemeProvider";

const LABELS: Record<ThemePreference, string> = {
  system: "System theme",
  light: "Light theme",
  dark: "Dark theme",
};

type ThemeSwitchProps = {
  className?: string;
  /** Icon-only control for primary headers (sun/moon/auto). */
  iconOnly?: boolean;
};

export function ThemeSwitch({ className, iconOnly = true }: ThemeSwitchProps) {
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
      title={LABELS[preference]}
      className={cn(
        "inline-flex items-center justify-center border border-jp-border bg-jp-surface text-jp-text",
        "transition-colors duration-ui hover:bg-jp-surface-muted",
        "focus-visible:outline-none focus-visible:shadow-jp-focus motion-reduce:transition-none",
        iconOnly
          ? "h-9 w-9 min-h-9 min-w-9 rounded-full"
          : "min-h-9 gap-1.5 rounded-jp-md px-2 py-1.5 text-jp-sm font-medium",
        className,
      )}
      aria-label={`Theme: ${LABELS[preference]}. Activate to switch theme.`}
      data-testid="theme-switch"
      data-theme-preference={preference}
    >
      <ThemeIcon preference={preference} />
      <span className="sr-only">{LABELS[preference]}</span>
    </button>
  );
}

function ThemeIcon({ preference }: { preference: ThemePreference }) {
  if (preference === "light") {
    return (
      <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
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
      <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
        <path
          d="M21 14.5A8.5 8.5 0 1 1 9.5 3a6.5 6.5 0 0 0 11.5 11.5Z"
          fill="currentColor"
        />
      </svg>
    );
  }

  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5 shrink-0" aria-hidden="true">
      <circle cx="12" cy="12" r="4" fill="currentColor" opacity="0.35" />
      <path
        d="M12 3v2.5M12 18.5V21M4.2 12H6.7M17.3 12H19.8M6.1 6.1l1.8 1.8M16.1 16.1l1.8 1.8M6.1 17.9l1.8-1.8M16.1 7.9l1.8-1.8"
        stroke="currentColor"
        strokeWidth="1.8"
        strokeLinecap="round"
      />
      <path d="M21 14.5A8.5 8.5 0 0 1 9.5 3" stroke="currentColor" strokeWidth="1.8" fill="none" />
    </svg>
  );
}
