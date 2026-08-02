/**
 * Durable JP-OPS-02 CSRF replay safety regression tests.
 */

import {
  CSRF_NO_AUTO_RETRY_PATH_PREFIXES,
  pathAllowsCsrfAutoRetry,
  simulateCsrfRetryPolicy,
} from "../../lib/api/csrf-retry-policy.mjs";

function mapStatusToErrorCode(status) {
  if (status === 419) return "csrf_expired";
  return "unknown";
}

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

// A. Default mutation: 419 normalized, no replay
{
  const { result, attempts } = await simulateCsrfRetryPolicy({
    method: "POST",
    retryCsrfOnce: false,
    fetchImpl: async () => ({ ok: false, code: mapStatusToErrorCode(419), status: 419 }),
  });
  assert(result.code === "csrf_expired", "A: 419 maps to csrf_expired");
  assert(attempts === 1, "A: exactly one mutation attempt");
}

// B. retryCsrfOnce: one refresh, at most two attempts, stops on second 419
{
  let csrfCalls = 0;
  const { result, attempts, csrfRefreshed } = await simulateCsrfRetryPolicy({
    method: "POST",
    retryCsrfOnce: true,
    fetchImpl: async ({ forceRefresh }) => {
      if (forceRefresh) csrfCalls += 1;
      return { ok: false, code: mapStatusToErrorCode(419), status: 419 };
    },
  });
  assert(csrfRefreshed, "B: CSRF refresh occurred");
  assert(csrfCalls === 1, "B: exactly one CSRF refresh");
  assert(attempts === 2, "B: at most two mutation attempts");
  assert(result.code === "csrf_expired", "B: second 419 stops with csrf_expired");
}

// B2. No infinite recursion when retryCsrfOnce stays true but keeps failing
{
  const { attempts } = await simulateCsrfRetryPolicy({
    method: "POST",
    retryCsrfOnce: true,
    fetchImpl: async () => ({ ok: false, code: "csrf_expired", status: 419 }),
  });
  assert(attempts === 2, "B2: policy caps at two attempts");
}

// C. Booking/payment paths must not opt in
for (const path of CSRF_NO_AUTO_RETRY_PATH_PREFIXES) {
  assert(!pathAllowsCsrfAutoRetry(path), `C: ${path} must not allow auto CSRF replay`);
  const { attempts } = await simulateCsrfRetryPolicy({
    method: "POST",
    retryCsrfOnce: false,
    fetchImpl: async () => ({ ok: false, code: "csrf_expired", status: 419, path }),
  });
  assert(attempts === 1, `C (${path}): exactly one attempt after 419`);
}

if (failed > 0) process.exit(1);
console.log("JP-OPS-02 CSRF replay regression: all cases passed");
