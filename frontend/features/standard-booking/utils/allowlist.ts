import { isAllowedInternalHandoffUrl } from "@/features/flight-details/utils/handoff";

const NEXT_BOOKING_PREFIXES = [
  "/booking/review",
  "/booking/payment",
  "/booking/payment/manual",
  "/booking/payment/card",
  "/booking/payment/status",
  "/booking/payment/return",
  "/booking/invoice",
  "/booking/confirmation",
  "/booking/one-api/",
] as const;

function pathMatchesAllowed(path: string): boolean {
  return NEXT_BOOKING_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`) || path.startsWith(`${prefix}?`));
}

/**
 * Validates Laravel-provided next_url before client navigation.
 */
export function isAllowedBookingNextUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  const trimmed = url.trim();
  if (!isAllowedInternalHandoffUrl(trimmed)) return false;

  if (trimmed.startsWith("/")) {
    return pathMatchesAllowed(trimmed);
  }

  try {
    const path = new URL(trimmed).pathname;
    return pathMatchesAllowed(path);
  } catch {
    return false;
  }
}

/**
 * Resolve Next.js booking handoff paths. Laravel Blade fallbacks remain on Laravel origin.
 */
export function resolveBookingNextUrl(pathOrUrl: string): string | null {
  if (!isAllowedBookingNextUrl(pathOrUrl)) return null;
  const normalized = pathOrUrl.startsWith("/") ? pathOrUrl : pathOrUrl.startsWith("http") ? pathOrUrl : `/${pathOrUrl}`;

  if (normalized.startsWith("http://") || normalized.startsWith("https://")) {
    try {
      const path = new URL(normalized).pathname;
      return pathMatchesAllowed(path) ? path : null;
    } catch {
      return null;
    }
  }

  return normalized;
}
