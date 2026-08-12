import { appConfig } from "@/lib/config";

const GUEST_BOOKING_ACCESS_PATH = /^\/guest\/bookings\/\d+\/access\/[A-Za-z0-9_-]+$/;

/**
 * Accept only Laravel-generated guest booking access redirects.
 */
export function resolveSafeGuestLookupRedirect(location: string | null): string | null {
  if (!location) return null;

  try {
    const url = new URL(location, window.location.origin);

    if (!GUEST_BOOKING_ACCESS_PATH.test(url.pathname)) {
      return null;
    }

    if (url.origin === window.location.origin) {
      return `${url.pathname}${url.search}`;
    }

    const laravelOrigin = new URL(appConfig.laravelUrl).origin;
    if (url.origin === laravelOrigin) {
      if (GUEST_BOOKING_ACCESS_PATH.test(url.pathname)) {
        return `${url.pathname}${url.search}`;
      }

      return `/laravel${url.pathname}${url.search}`;
    }

    return null;
  } catch {
    return null;
  }
}

export const GENERIC_LOOKUP_FAILURE =
  "Booking not found for the provided reference and email.";

export const RATE_LIMIT_MESSAGE =
  "Too many lookup attempts. Please wait a moment and try again.";

export const TURNSTILE_FAILURE_MESSAGE =
  "Security check failed. Please refresh and try again.";

/** @deprecated Removed from UI — modern lookup is /lookup-booking only. Kept for test imports. */
export const BLADE_LOOKUP_FALLBACK_PATH = "/lookup-booking";
