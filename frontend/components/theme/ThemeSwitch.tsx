"use client";

import { THEME_CYCLE_VALUES, type ThemePreference } from "@/lib/theme/constants";
import { cn } from "@/lib/cn";
import { UI_ICON_NAV_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import { Moon, Sun } from "lucide-react";
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
      <Moon className={UI_ICON_NAV_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
    );
  }

  return (
    <Sun className={UI_ICON_NAV_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
  );
}
