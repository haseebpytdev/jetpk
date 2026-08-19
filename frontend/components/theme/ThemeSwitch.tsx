"use client";

import { THEME_CYCLE_VALUES, type ThemePreference } from "@/lib/theme/constants";
import { cn } from "@/lib/cn";
import { useTheme } from "./ThemeProvider";

const LABELS: Record<"light" | "dark", string> = {
  light: "Day theme",
  dark: "Night theme",
};

type ThemeSwitchProps = {
  className?: string;
  /** Icon-only control for primary headers (sun/moon/auto). */
  iconOnly?: boolean;
};

export function ThemeSwitch({ className, iconOnly = true }: ThemeSwitchProps) {
  const { preference, setPreference } = useTheme();
  const mode: "light" | "dark" = preference === "dark" ? "dark" : "light";

  const cyclePreference = () => {
    const index = THEME_CYCLE_VALUES.indexOf(mode);
    const next = THEME_CYCLE_VALUES[(index + 1) % THEME_CYCLE_VALUES.length];
    setPreference(next);
  };

  return (
    <button
      type="button"
      onClick={cyclePreference}
      title={LABELS[mode]}
      className={cn(
        "inline-flex items-center justify-center border border-jp-border bg-jp-surface text-jp-text",
        "transition-colors duration-ui hover:bg-jp-surface-muted",
        "focus-visible:outline-none focus-visible:shadow-jp-focus motion-reduce:transition-none",
        iconOnly
          ? "h-9 w-9 min-h-9 min-w-9 rounded-full"
          : "min-h-9 gap-1.5 rounded-jp-md px-2 py-1.5 text-jp-sm font-medium",
        className,
      )}
      aria-label={`Theme: ${LABELS[mode]}. Activate to switch theme.`}
      data-testid="theme-switch"
      data-theme-preference={mode}
    >
      <ThemeIcon preference={mode} />
      <span className="sr-only">{LABELS[mode]}</span>
    </button>
  );
}

function ThemeIcon({ preference }: { preference: ThemePreference }) {
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
