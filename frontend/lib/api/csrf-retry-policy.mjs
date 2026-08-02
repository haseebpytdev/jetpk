/**
 * Shared CSRF retry policy for laravel-action-client and regression tests.
 * Keep behavior aligned with frontend/lib/api/laravel-action-client.ts.
 */

/**
 * @param {{ ok: boolean; code?: string }} result
 * @param {string} method
 * @param {boolean} retryCsrfOnce
 */
export function shouldRetryAfterCsrfExpired(result, method, retryCsrfOnce) {
  return (
    !result.ok &&
    result.code === "csrf_expired" &&
    method !== "GET" &&
    retryCsrfOnce === true
  );
}

/**
 * Simulates the client retry policy for deterministic regression tests.
 *
 * @param {{
 *   method?: string;
 *   retryCsrfOnce?: boolean;
 *   fetchImpl: (ctx: { attempt: number; forceRefresh: boolean }) => Promise<{ ok: boolean; code?: string; status?: number; path?: string }>;
 * }} options
 */
export async function simulateCsrfRetryPolicy(options) {
  const method = options.method ?? "GET";
  const retryCsrfOnce = options.retryCsrfOnce ?? false;
  let attempts = 0;
  let csrfRefreshed = false;

  const run = async (forceRefresh = false) => {
    attempts += 1;
    if (forceRefresh) csrfRefreshed = true;
    return options.fetchImpl({ attempt: attempts, forceRefresh });
  };

  let result = await run(false);

  if (shouldRetryAfterCsrfExpired(result, method, retryCsrfOnce)) {
    result = await run(true);
  }

  return { result, attempts, csrfRefreshed };
}

/**
 * Paths that must never opt into automatic CSRF replay.
 */
export const CSRF_NO_AUTO_RETRY_PATH_PREFIXES = [
  "/payments/",
  "/bookings/hold",
  "/bookings/confirm",
  "/booking/",
];

export function pathAllowsCsrfAutoRetry(path) {
  return !CSRF_NO_AUTO_RETRY_PATH_PREFIXES.some((prefix) => path.startsWith(prefix));
}
