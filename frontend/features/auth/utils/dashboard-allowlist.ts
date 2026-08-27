import { isAllowedCheckoutReturn } from "./checkout-return-allowlist";

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
  "/customer/dashboard",
  "/agent",
  "/agent/dashboard",
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

  let [pathOnly, query = ""] = trimmed.split("?");
  if (pathOnly === "/jetpk") {
    pathOnly = "/";
  } else if (pathOnly.startsWith("/jetpk/")) {
    pathOnly = pathOnly.slice("/jetpk".length);
  }
  const suffix = query ? `?${query}` : "";
  const candidate = `${pathOnly}${suffix}`;

  if (EXACT_PATHS.has(pathOnly)) return candidate;
  if (PREFIXES.filter((prefix) => prefix !== "/jetpk/").some((prefix) => pathOnly.startsWith(prefix))) {
    return candidate;
  }

  // Group / flight checkout resume (Book Now modal → post-login).
  if (isAllowedCheckoutReturn(candidate) || isAllowedCheckoutReturn(pathOnly)) {
    return candidate;
  }

  return fallback;
}

export function isNextJsOwnedPath(path: string): boolean {
  const [pathOnly] = path.split("?");
  return (
    pathOnly === "/customer" ||
    pathOnly === "/agent" ||
    pathOnly.startsWith("/customer/") ||
    pathOnly.startsWith("/agent/")
  );
}
