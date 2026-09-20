import type { LaravelManagedPageResponse } from "../types";
import { publicContentFetchUrl } from "./laravel-api-url";

const LARAVEL_FETCH_TIMEOUT_MS = 3_000;

/** Browser managed-page fetch — safe for client components (no React cache). */
export async function fetchManagedPageBrowser(
  pageKey: string,
): Promise<LaravelManagedPageResponse | null> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), LARAVEL_FETCH_TIMEOUT_MS);
  try {
    const response = await fetch(publicContentFetchUrl(`/api/public/content/pages/${pageKey}`), {
      headers: { Accept: "application/json" },
      credentials: typeof window !== "undefined" ? "include" : "omit",
      signal: controller.signal,
      cache: "no-store",
    });
    if (!response.ok) return null;
    return (await response.json()) as LaravelManagedPageResponse;
  } catch {
    return null;
  } finally {
    clearTimeout(timeout);
  }
}
