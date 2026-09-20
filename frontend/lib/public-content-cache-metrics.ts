/**
 * Server-side counters for public CMS cache instrumentation.
 * Log lines are structured for soft-nav / deploy evidence (no PII).
 */

type Metrics = {
  laravelPublicPageCalls: number;
  laravelPublicConfigCalls: number;
};

const metrics: Metrics = {
  laravelPublicPageCalls: 0,
  laravelPublicConfigCalls: 0,
};

export function recordLaravelPublicPageCall(pageKey: string, cacheStatus: "HIT" | "MISS" | "BYPASS"): void {
  if (cacheStatus === "MISS" || cacheStatus === "BYPASS") {
    metrics.laravelPublicPageCalls += 1;
  }
  console.info(
    JSON.stringify({
      scope: "jp-public-content",
      resource: "managed_page",
      pageKey,
      CACHE_STATUS: cacheStatus,
      LARAVEL_PUBLIC_PAGE_CALLS: metrics.laravelPublicPageCalls,
    }),
  );
}

export function recordLaravelPublicConfigCall(cacheStatus: "HIT" | "MISS" | "BYPASS"): void {
  if (cacheStatus === "MISS" || cacheStatus === "BYPASS") {
    metrics.laravelPublicConfigCalls += 1;
  }
  console.info(
    JSON.stringify({
      scope: "jp-public-content",
      resource: "public_config",
      CACHE_STATUS: cacheStatus,
      LARAVEL_PUBLIC_CONFIG_CALLS: metrics.laravelPublicConfigCalls,
    }),
  );
}

export function getPublicContentCacheMetrics(): Readonly<Metrics> {
  return { ...metrics };
}
