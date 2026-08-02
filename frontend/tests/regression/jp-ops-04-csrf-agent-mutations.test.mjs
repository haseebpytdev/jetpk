/**
 * JP-OPS-04: prove agent portal mutations attempt once on 419 (no auto replay).
 */

import test from "node:test";
import assert from "node:assert/strict";
import { simulateCsrfRetryPolicy } from "../../lib/api/csrf-retry-policy.mjs";

const AGENT_MUTATION_PATHS = [
  { name: "booking cancellation", path: "/agent/bookings/BKG-1001/cancellations?format=json", method: "POST" },
  { name: "deposit submit", path: "/agent/deposits?format=json", method: "POST" },
  { name: "staff create", path: "/agent/staff?format=json", method: "POST" },
  { name: "staff update", path: "/agent/staff/12?format=json", method: "POST" },
  { name: "staff permissions", path: "/agent/staff/12/permissions?format=json", method: "POST" },
  { name: "payment proof", path: "/agent/bookings/BKG-1001/payment-proof?format=json", method: "POST" },
  { name: "support reply", path: "/agent/support/tickets/TKT-1001/reply?format=json", method: "POST" },
  { name: "agency update", path: "/agent/agency?format=json", method: "POST" },
];

for (const mutation of AGENT_MUTATION_PATHS) {
  test(`419 on ${mutation.name} produces exactly one mutation attempt`, async () => {
    const { result, attempts } = await simulateCsrfRetryPolicy({
      method: mutation.method,
      retryCsrfOnce: false,
      fetchImpl: async () => ({
        ok: false,
        code: "csrf_expired",
        status: 419,
        path: mutation.path,
      }),
    });

    assert.equal(result.code, "csrf_expired");
    assert.equal(attempts, 1, `${mutation.name} must not auto-replay after 419`);
  });
}
