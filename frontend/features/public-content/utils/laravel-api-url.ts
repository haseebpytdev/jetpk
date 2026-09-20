import { absoluteLaravelUrl, laravelApiPath } from "@/services/flight-search";

/**
 * Server components must call Laravel directly (runtime LARAVEL_URL) because
 * Next rewrites are baked at build time and can target the wrong loopback host.
 */
export function publicContentFetchUrl(apiPath: string): string {
  const normalized = apiPath.startsWith("/") ? apiPath : `/${apiPath}`;
  if (typeof window === "undefined") {
    return absoluteLaravelUrl(normalized);
  }

  return laravelApiPath(normalized);
}
