export function shouldRetryAfterCsrfExpired(
  result: { ok: boolean; code?: string },
  method: string,
  retryCsrfOnce: boolean,
): boolean {
  return !result.ok && result.code === "csrf_expired" && method !== "GET" && retryCsrfOnce === true;
}

export const CSRF_NO_AUTO_RETRY_PATH_PREFIXES = [
  "/admin/bookings/payments/",
  "/staff/bookings/payments/",
  "/admin/agent-deposits/",
  "/admin/bookings/cancellations/",
  "/admin/bookings/refunds/",
  "/staff/bookings/cancellations/",
  "/staff/bookings/refunds/",
  "/admin/users/",
  "/staff/bookings/issue-ticket",
  "/admin/bookings/issue-ticket",
];

export function pathAllowsCsrfAutoRetry(path: string): boolean {
  return !CSRF_NO_AUTO_RETRY_PATH_PREFIXES.some((prefix) => path.startsWith(prefix));
}
