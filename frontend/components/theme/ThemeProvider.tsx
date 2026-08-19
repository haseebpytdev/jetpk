"use client";

import {
  DEFAULT_THEME_PREFERENCE,
  isThemePreference,
  resolveTheme,
  THEME_STORAGE_KEY,
  type ResolvedTheme,
  type ThemePreference,
} from "@/lib/theme/constants";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

type ThemeContextValue = {
  preference: ResolvedTheme;
  resolved: ResolvedTheme;
  setPreference: (preference: ThemePreference) => void;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

function readStoredPreference(): ThemePreference {
  if (typeof window === "undefined") return DEFAULT_THEME_PREFERENCE;
  try {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);
    return isThemePreference(stored) ? stored : DEFAULT_THEME_PREFERENCE;
  } catch {
    return DEFAULT_THEME_PREFERENCE;
  }
}

function applyResolvedTheme(resolved: ResolvedTheme) {
  document.documentElement.setAttribute("data-theme", resolved);
  document.documentElement.style.colorScheme = resolved;
}

function normalizePreference(preference: ThemePreference): "light" | "dark" {
  return preference === "dark" ? "dark" : "light";
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreferenceState] = useState<ThemePreference>(DEFAULT_THEME_PREFERENCE);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    const stored = readStoredPreference();
    // Legacy "system" must not follow OS — coerce to light (DAY).
    setPreferenceState(normalizePreference(stored));
    setMounted(true);
  }, []);

  const resolved = resolveTheme(preference);

  useEffect(() => {
    applyResolvedTheme(resolved);
    if (!mounted) return;
    try {
      localStorage.setItem(THEME_STORAGE_KEY, normalizePreference(preference));
    } catch {
      /* storage unavailable */
    }
  }, [preference, resolved, mounted]);

  const setPreference = useCallback((next: ThemePreference) => {
    const normalized = normalizePreference(next);
    setPreferenceState(normalized);
    applyResolvedTheme(resolveTheme(normalized));
    try {
      localStorage.setItem(THEME_STORAGE_KEY, normalized);
    } catch {
      /* storage unavailable */
    }
  }, []);

  const value = useMemo(
    () => ({ preference: normalizePreference(preference), resolved, setPreference }),
    [preference, resolved, setPreference],
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error("useTheme must be used within ThemeProvider");
  }
  return context;
}
