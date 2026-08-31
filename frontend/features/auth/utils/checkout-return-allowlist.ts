/**
 * Mirrors Laravel CheckoutReturnIntent for client-side resume path checks.
 * Never accept absolute URLs — open-redirect safe.
 */
export function isAllowedCheckoutReturn(target: string | null | undefined): boolean {
  if (!target) return false;
  const trimmed = target.trim();
  if (!trimmed.startsWith("/") || trimmed.startsWith("//") || trimmed.includes("://")) {
    return false;
  }
  if (/[\n\r]/.test(trimmed)) return false;

  const pathOnly = trimmed.split("?")[0] ?? "";
  if (pathOnly.startsWith("/booking/passengers")) return true;
  if (pathOnly.startsWith("/booking/account")) return true;
  if (pathOnly.startsWith("/booking/review")) return true;
  if (/^\/groups\/[^/]+\/passengers$/.test(pathOnly)) return true;
  if (/^\/groups\/booking\/[^/]+\/(review|payment|confirmation|status)$/.test(pathOnly)) return true;
  if (/^\/customer\/bookings\/\d+$/.test(pathOnly)) return true;
  return false;
}

export function sanitizeCheckoutReturnUrl(
  path: string | undefined | null,
  fallback = "/",
): string {
  if (!path) return fallback;
  const trimmed = path.trim();
  if (!isAllowedCheckoutReturn(trimmed)) return fallback;
  return trimmed;
}
