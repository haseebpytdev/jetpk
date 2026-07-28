const EXACT_PATHS = new Set([
  "/",
  "/login",
  "/login/otp",
  "/register",
  "/agent/register",
  "/agent/register/submitted",
  "/forgot-password",
  "/verify-email",
  "/password/force-change",
  "/customer",
  "/agent",
  "/admin/dashboard",
  "/staff/dashboard",
  "/account/legacy",
]);

const PREFIXES = ["/customer/", "/agent/", "/admin/", "/staff/", "/reset-password/", "/verify-email/", "/jetpk/"];

export function sanitizeDashboardUrl(path: string | undefined | null, fallback = "/"): string {
  if (!path) return fallback;
  const trimmed = path.trim();
  if (!trimmed.startsWith("/") || trimmed.includes("//") || trimmed.includes("\\")) {
    return fallback;
  }

  const [pathOnly] = trimmed.split("?");
  if (EXACT_PATHS.has(pathOnly)) return trimmed;
  if (PREFIXES.some((prefix) => pathOnly.startsWith(prefix))) return trimmed;

  return fallback;
}

export function isNextJsOwnedPath(path: string): boolean {
  const [pathOnly] = path.split("?");
  return pathOnly === "/customer" || pathOnly === "/agent" || pathOnly.startsWith("/customer/");
}
