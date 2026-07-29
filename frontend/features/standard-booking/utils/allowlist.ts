import { isAllowedInternalHandoffUrl } from "@/features/flight-details/utils/handoff";
import { absoluteLaravelHandoffUrl } from "@/services/flight-search";

/**
 * Validates Laravel-provided next_url before client navigation.
 */
export function isAllowedBookingNextUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  const trimmed = url.trim();
  if (!isAllowedInternalHandoffUrl(trimmed)) return false;
  if (trimmed.startsWith("/")) {
    return (
      trimmed.startsWith("/booking/review") ||
      trimmed.startsWith("/booking/confirmation") ||
      trimmed.startsWith("/booking/one-api/")
    );
  }
  try {
    const path = new URL(trimmed).pathname;
    return (
      path.startsWith("/booking/review") ||
      path.startsWith("/booking/confirmation") ||
      path.startsWith("/booking/one-api/")
    );
  } catch {
    return false;
  }
}

/**
 * Review/payment/confirmation remain Laravel-owned until JP-FE-09.
 */
export function resolveBookingNextUrl(pathOrUrl: string): string | null {
  if (!isAllowedBookingNextUrl(pathOrUrl)) return null;
  if (pathOrUrl.startsWith("http://") || pathOrUrl.startsWith("https://")) {
    return pathOrUrl;
  }
  const normalized = pathOrUrl.startsWith("/") ? pathOrUrl : `/${pathOrUrl}`;
  if (
    normalized.startsWith("/booking/review") ||
    normalized.startsWith("/booking/confirmation") ||
    normalized.startsWith("/booking/one-api/")
  ) {
    return absoluteLaravelHandoffUrl(normalized);
  }
  return normalized;
}
