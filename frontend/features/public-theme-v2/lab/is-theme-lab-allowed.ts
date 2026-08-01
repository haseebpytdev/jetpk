/**
 * Server-side gate for the JetPakistan theme visual lab.
 * Allowed in non-production or when JP_THEME_LAB_ENABLED=true.
 */
export function isThemeLabAllowed(): boolean {
  if (process.env.JP_THEME_LAB_ENABLED === "true") {
    return true;
  }
  return process.env.NODE_ENV !== "production";
}
