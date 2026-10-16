import { appConfig } from "@/lib/config";

/** Clean token-free guest booking path after lookup / exchange. */
const GUEST_BOOKING_CLEAN_PATH = /^\/guest\/bookings\/\d+$/;
/** Legacy path still accepted so we can navigate into Laravel exchange once. */
const GUEST_BOOKING_LEGACY_ACCESS_PATH = /^\/guest\/bookings\/\d+\/access\/[A-Za-z0-9_-]+$/;

/**
 * Accept only Laravel-generated guest booking access redirects.
 * Prefer token-free paths; legacy token paths are allowed as a one-hop exchange entry.
 */
export function resolveSafeGuestLookupRedirect(location: string | null): string | null {
  if (!location) return null;

  try {
    const url = new URL(location, window.location.origin);
    const isClean = GUEST_BOOKING_CLEAN_PATH.test(url.pathname);
    const isLegacy = GUEST_BOOKING_LEGACY_ACCESS_PATH.test(url.pathname);

    if (!isClean && !isLegacy) {
      return null;
    }

    if (url.origin === window.location.origin) {
      // Prefer clean URLs in the browser; legacy URLs go through /laravel exchange.
      if (isLegacy) {
        return `/laravel${url.pathname}${url.search}`;
      }

      return `${url.pathname}${url.search}`;
    }

    const laravelOrigin = new URL(appConfig.laravelUrl).origin;
    if (url.origin === laravelOrigin) {
      if (isLegacy || isClean) {
        return `/laravel${url.pathname}${url.search}`;
      }

      return null;
    }

    return null;
  } catch {
    return null;
  }
}

export const BLADE_LOOKUP_FALLBACK_PATH = "/laravel/lookup-booking";

export const GENERIC_LOOKUP_FAILURE =
  "Booking not found for the provided reference and email.";

export const RATE_LIMIT_MESSAGE =
  "Too many lookup attempts. Please wait a moment and try again.";

export const TURNSTILE_FAILURE_MESSAGE =
  "Security check failed. Please refresh and try again.";
