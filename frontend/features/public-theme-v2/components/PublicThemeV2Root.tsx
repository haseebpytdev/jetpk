"use client";

import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from "react";
import "../styles/tokens.css";
import "../styles/theme.css";

export type ThemeV2Mode = "light" | "dark";

type ThemeV2ContextValue = {
  theme: ThemeV2Mode;
  setTheme: (theme: ThemeV2Mode) => void;
  toggleTheme: () => void;
};

const ThemeV2Context = createContext<ThemeV2ContextValue | null>(null);

export function useThemeV2(): ThemeV2ContextValue {
  const ctx = useContext(ThemeV2Context);
  if (!ctx) {
    throw new Error("useThemeV2 must be used within PublicThemeV2Root");
  }
  return ctx;
}

type PublicThemeV2RootProps = {
  children: ReactNode;
  initialTheme?: ThemeV2Mode;
  className?: string;
};

export function PublicThemeV2Root({
  children,
  initialTheme = "light",
  className,
}: PublicThemeV2RootProps) {
  const [theme, setTheme] = useState<ThemeV2Mode>(initialTheme);

  const toggleTheme = useCallback(() => {
    setTheme((prev) => (prev === "light" ? "dark" : "light"));
  }, []);

  const value = useMemo(
    () => ({ theme, setTheme, toggleTheme }),
    [theme, toggleTheme],
  );

  return (
    <ThemeV2Context.Provider value={value}>
      <div
        className={["jp-theme-v2", className].filter(Boolean).join(" ")}
        data-jp-theme={theme}
        data-testid="jp-theme-v2-root"
      >
        {children}
      </div>
    </ThemeV2Context.Provider>
  );
}
