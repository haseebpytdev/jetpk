/**
 * JP-OPS-05 CSRF no-replay policy regression tests.
 */

import {
  CSRF_NO_AUTO_RETRY_PATH_PREFIXES,
  pathAllowsCsrfAutoRetry,
  shouldRetryAfterCsrfExpired,
} from "../../lib/api/csrf-retry-policy.ts";

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

assert(
  shouldRetryAfterCsrfExpired({ ok: false, code: "csrf_expired" }, "PATCH", false) === false,
  "connected mutations must not auto-retry CSRF",
);

assert(
  shouldRetryAfterCsrfExpired({ ok: false, code: "csrf_expired" }, "PATCH", true) === true,
  "explicit retryCsrfOnce=true may retry",
);

for (const prefix of CSRF_NO_AUTO_RETRY_PATH_PREFIXES) {
  assert(
    pathAllowsCsrfAutoRetry(`${prefix}123/verify`) === false,
    `blocked prefix ${prefix}`,
  );
}

assert(pathAllowsCsrfAutoRetry("/api/dashboard/session") === true, "read paths may allow CSRF retry");

console.log(`jp-ops-05-csrf-replay: ${failed === 0 ? "PASS" : `${failed} FAIL`}`);
process.exit(failed > 0 ? 1 : 0);
