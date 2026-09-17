/** Fallback when Company Profile has not published a favicon URL. */
export const CANONICAL_JETPK_FAVICON_PATH = "/favicon.ico";

/**
 * Resolve favicon href for Next metadata from Company Profile public config.
 * Absolute storage URLs and relative /storage paths are accepted; anything else
 * falls back to the static Next favicon (which should mirror client-assets).
 */
export function resolveFaviconUrl(faviconUrl?: string | null): string {
  const trimmed = faviconUrl?.trim() ?? "";
  if (trimmed === "") {
    return CANONICAL_JETPK_FAVICON_PATH;
  }

  if (trimmed.startsWith("http://") || trimmed.startsWith("https://")) {
    try {
      const url = new URL(trimmed);
      if (
        url.pathname.includes("/storage/") ||
        url.pathname.includes("/agencies/") ||
        url.pathname.includes("/client-assets/") ||
        url.pathname.endsWith("favicon.ico") ||
        url.pathname.includes("/favicon/")
      ) {
        return trimmed;
      }
    } catch {
      return CANONICAL_JETPK_FAVICON_PATH;
    }
    return CANONICAL_JETPK_FAVICON_PATH;
  }

  if (trimmed.startsWith("/")) {
    return trimmed;
  }

  if (trimmed.startsWith("storage/") || trimmed.startsWith("client-assets/")) {
    return `/${trimmed}`;
  }

  return CANONICAL_JETPK_FAVICON_PATH;
}
