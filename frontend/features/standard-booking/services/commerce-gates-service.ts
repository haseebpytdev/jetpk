import { laravelApiPath } from "@/services/flight-search";
import { fetchSessionBootstrap } from "@/features/auth/services/session-service";
import { fetchWithTimeout } from "@/features/public-content/utils/laravel-api";

export type CommerceGates = {
  guest_booking_enabled: boolean;
  card_payment_enabled: boolean;
  customer_group_booking_enabled: boolean;
  customer_registration_enabled: boolean;
};

const DEFAULT_GATES: CommerceGates = {
  guest_booking_enabled: true,
  card_payment_enabled: true,
  customer_group_booking_enabled: true,
  customer_registration_enabled: true,
};

let cachedGates: CommerceGates | null = null;

export function clearCommerceGatesCache(): void {
  cachedGates = null;
}

export async function fetchCommerceGates(): Promise<CommerceGates> {
  if (cachedGates) {
    return cachedGates;
  }

  try {
    const response = await fetchWithTimeout(laravelApiPath("/booking/commerce-gates"), {
      headers: { Accept: "application/json" },
      cache: "no-store",
    });
    if (!response.ok) {
      return DEFAULT_GATES;
    }
    const payload = (await response.json()) as Partial<CommerceGates>;
    cachedGates = {
      guest_booking_enabled: payload.guest_booking_enabled !== false,
      card_payment_enabled: payload.card_payment_enabled !== false,
      customer_group_booking_enabled: payload.customer_group_booking_enabled !== false,
      customer_registration_enabled: payload.customer_registration_enabled !== false,
    };
    return cachedGates;
  } catch {
    return DEFAULT_GATES;
  }
}

export function buildGuestCheckoutAuthRedirect(targetPath: string): string {
  return `/booking/account?redirect=${encodeURIComponent(targetPath)}`;
}

export async function ensureGuestBookingAllowed(
  targetPath: string,
  isAuthenticated: boolean,
): Promise<string | null> {
  if (isAuthenticated) {
    return null;
  }

  const gates = await fetchCommerceGates();
  if (gates.guest_booking_enabled) {
    return null;
  }

  return buildGuestCheckoutAuthRedirect(targetPath);
}

export async function redirectIfGuestBookingBlocked(targetPath: string): Promise<boolean> {
  try {
    const bootstrap = await fetchSessionBootstrap();
    const redirect = await ensureGuestBookingAllowed(targetPath, Boolean(bootstrap.authenticated));
    if (!redirect) {
      return false;
    }
    window.location.assign(redirect);
    return true;
  } catch {
    // Fail open: guest checkout is the primary path; never block Book Now on gate I/O.
    return false;
  }
}
