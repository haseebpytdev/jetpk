export {
  JETPK_FONT_CSS_VARS,
  JETPK_LEGACY_FONT_BODY_FAMILY,
  JETPK_LEGACY_FONT_DISPLAY_FAMILY,
  JETPK_LEGACY_FONT_MONO_FAMILY,
  JETPK_TYPOGRAPHY_CONTRACT,
} from "./typography";

export const THEME_STORAGE_KEY = "jp-theme-preference";

/** Internal day/night preferences only. Legacy "system" is accepted then coerced to light. */
export const THEME_VALUES = ["light", "dark"] as const;

export const THEME_CYCLE_VALUES = THEME_VALUES;

export type ThemePreference = (typeof THEME_VALUES)[number] | "system";

export type ResolvedTheme = "light" | "dark";

export const DEFAULT_THEME_PREFERENCE: ThemePreference = "light";

export function isThemePreference(value: unknown): value is ThemePreference {
  return value === "light" || value === "dark" || value === "system";
}

/**
 * Resolve visual theme. Browser/OS preference never activates dark unless the
 * internal preference is explicitly "dark". Legacy "system" maps to light.
 */
export function resolveTheme(preference: ThemePreference, _systemDark = false): ResolvedTheme {
  if (preference === "dark") return "dark";
  return "light";
}
