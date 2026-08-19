import { appConfig } from "@/lib/config";

/**
 * Mirrors Laravel PublicFlightSearchSecurity::isAllowedInternalUrl for passenger handoff.
 */
export function isAllowedInternalHandoffUrl(url: string | null | undefined): boolean {
  if (!url) return false;
  const trimmed = url.trim();
  if (trimmed === "") return false;
  if (/^\s*(javascript|data|vbscript):/i.test(trimmed)) return false;
  if (trimmed.startsWith("//")) return false;
  if (trimmed.startsWith("/")) return !trimmed.includes("..");
  if (/^https?:\/\//i.test(trimmed)) {
    try {
      const appHost = new URL(appConfig.laravelUrl).hostname;
      const urlHost = new URL(trimmed).hostname;
      return appHost !== "" && urlHost.toLowerCase() === appHost.toLowerCase();
    } catch {
      return false;
    }
  }
  return false;
}

export function resolveHandoffUrl(pathOrUrl: string): string | null {
  if (!isAllowedInternalHandoffUrl(pathOrUrl)) return null;
  if (pathOrUrl.startsWith("http://") || pathOrUrl.startsWith("https://")) {
    return pathOrUrl;
  }
  const base = appConfig.laravelUrl.replace(/\/$/, "");
  const normalized = pathOrUrl.startsWith("/") ? pathOrUrl : `/${pathOrUrl}`;
  return `${base}${normalized}`;
}

/**
 * Passenger checkout handoff prefers the Next.js route when the path is /booking/passengers.
 */
export function resolvePassengerCheckoutHandoffUrl(pathOrUrl: string): string | null {
  if (!isAllowedInternalHandoffUrl(pathOrUrl)) return null;

  const normalized = pathOrUrl.startsWith("http://") || pathOrUrl.startsWith("https://")
    ? (() => {
        try {
          const url = new URL(pathOrUrl);
          return `${url.pathname}${url.search}`;
        } catch {
          return pathOrUrl;
        }
      })()
    : pathOrUrl.startsWith("/")
      ? pathOrUrl
      : `/${pathOrUrl}`;

  if (normalized.startsWith("/booking/passengers") || /(?:^|\/)booking\/passengers(?:\?|$)/.test(normalized)) {
    const match = normalized.match(/(\/booking\/passengers(?:\?.*)?)$/);
    return match ? match[1] : "/booking/passengers";
  }

  return resolveHandoffUrl(pathOrUrl);
}

export function providerRequiresRevalidation(provider?: string): boolean {
  const lc = (provider ?? "").toLowerCase();
  return lc === "iati" || lc === "sabre";
}
