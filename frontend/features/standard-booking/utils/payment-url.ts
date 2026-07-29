import { laravelApiPath } from "@/services/flight-search";

const ALLOWED_HOSTS = new Set(["abhipay.com.pk", "www.abhipay.com.pk", "sandbox.abhipay.com.pk"]);

/**
 * Only navigate to hosted checkout URLs returned by Laravel after card initiation.
 */
export function isAllowedHostedCheckoutUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "https:") return false;
    const host = parsed.hostname.toLowerCase();
    return ALLOWED_HOSTS.has(host) || host.endsWith(".abhipay.com.pk");
  } catch {
    return false;
  }
}

export function resolveLaravelPostUrl(path: string): string | null {
  if (!path.startsWith("/")) return null;
  if (path.includes("/abhipay/start") || path.includes("/guest/bookings/")) {
    return laravelApiPath(path.split("?")[0]);
  }
  return null;
}

export function isAllowedConfirmationHandoff(path: string | null | undefined): boolean {
  if (!path) return false;
  const normalized = path.trim();
  return normalized === "/booking/confirmation" || normalized.startsWith("/booking/confirmation?");
}
