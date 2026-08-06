import { isAllowedInternalHandoffUrl } from "@/features/flight-details/utils/handoff";

/**
 * Normalize Laravel results search_url to a Next-owned /flights/results path.
 */
export function resolveNearbyDateResultsPath(searchUrl: string): string | null {
  if (!isAllowedInternalHandoffUrl(searchUrl)) return null;

  try {
    const parsed = searchUrl.startsWith("http")
      ? new URL(searchUrl)
      : new URL(searchUrl, "http://localhost");
    if (!parsed.pathname.endsWith("/flights/results") && parsed.pathname !== "/flights/results") {
      return null;
    }
    return `/flights/results${parsed.search}`;
  } catch {
    return null;
  }
}
