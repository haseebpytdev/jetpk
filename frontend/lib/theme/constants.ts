export {
  JETPK_FONT_CSS_VARS,
  JETPK_LEGACY_FONT_BODY_FAMILY,
  JETPK_LEGACY_FONT_DISPLAY_FAMILY,
  JETPK_LEGACY_FONT_MONO_FAMILY,
  JETPK_TYPOGRAPHY_CONTRACT,
} from "./typography";

export const THEME_STORAGE_KEY = "jp-theme-preference";

export const THEME_VALUES = ["system", "light", "dark"] as const;

export type ThemePreference = (typeof THEME_VALUES)[number];

export type ResolvedTheme = "light" | "dark";

export const DEFAULT_THEME_PREFERENCE: ThemePreference = "system";

export function isThemePreference(value: unknown): value is ThemePreference {
  return typeof value === "string" && (THEME_VALUES as readonly string[]).includes(value);
}

export function resolveTheme(preference: ThemePreference, systemDark: boolean): ResolvedTheme {
  if (preference === "dark") return "dark";
  if (preference === "light") return "light";
  return systemDark ? "dark" : "light";
}
